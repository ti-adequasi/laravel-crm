<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\LeadGreen\Models\LeadGreen;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\WebForm\Models\WebForm;

uses(DatabaseTransactions::class);

// Named distinctly from TenantScopingTest.php's own createTenantScopingTestX()
// helpers (same shape) rather than relying on them: Pest loads every test
// file's top-level declarations before any test runs, so reusing those
// names would work in a full-suite run but fatal on "Cannot redeclare" if
// both files ever declared the same name, and break entirely when this
// file runs on its own (e.g. --filter) before the other file loads.
function makeLeakageTestPipeline(int $tenantId): Pipeline
{
    $pipeline = Pipeline::create([
        'tenant_id' => $tenantId,
        'name' => 'Leakage Test Pipeline '.uniqid(),
        'rotten_days' => 30,
        'is_default' => true,
    ]);

    Stage::create([
        'tenant_id' => $tenantId,
        'code' => 'new',
        'name' => 'New',
        'probability' => 100,
        'sort_order' => 1,
        'lead_pipeline_id' => $pipeline->id,
    ]);

    return $pipeline->fresh();
}

function makeLeakageTestLead(string $title, int $pipelineId): Lead
{
    return app(LeadRepository::class)->create([
        'title' => $title,
        'lead_value' => 100,
        'lead_pipeline_id' => $pipelineId,
        'lead_pipeline_stage_id' => Pipeline::find($pipelineId)->stages->first()->id,
        'status' => 1,
        'person' => [
            'name' => 'Leakage Test Person',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
            'entity_type' => 'persons',
        ],
        'entity_type' => 'leads',
    ]);
}

/**
 * Phase 2.6 — systematic cross-tenant leakage pass. Each test below
 * targets one specific gap a real audit found in the surfaces Phase
 * 2.1-2.5 didn't directly exercise: routes several packages register
 * independently of AdminServiceProvider's own group (so 'tenant' was
 * never applied to them at all), and a raw DB::table() query with no
 * tenant filter of its own. Every other tenant-owned model's DataGrid,
 * direct-view route and export path was individually audited and
 * confirmed already safe via the same BelongsToTenant + ScopeDataGridToTenant
 * mechanism proven in tests/Feature/TenantScopingTest.php — this file
 * covers the surfaces that mechanism does not, by construction, reach.
 */
function makeLeakageTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Leakage Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Leakage Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

it('refuses a tenant-scoped user viewing, converting, enriching or discarding another tenant\'s LeadGreen prospect', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'LeadGreen Leak A', 'code' => 'leadgreen-leak-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'LeadGreen Leak B', 'code' => 'leadgreen-leak-b-'.uniqid(), 'is_active' => true]);

    $userB = makeLeakageTestTenantUser($tenantB->id);

    $prospectA = LeadGreen::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Tenant A Prospect',
        'website' => 'https://tenant-a-prospect.example',
    ]);

    test()->actingAs($userB)->get(route('admin.leadgreen.view', $prospectA->id))->assertNotFound();
    test()->actingAs($userB)->get(route('admin.leadgreen.convert', $prospectA->id))->assertNotFound();
    test()->actingAs($userB)->post(route('admin.leadgreen.enrich', $prospectA->id))->assertNotFound();
    test()->actingAs($userB)->post(route('admin.leadgreen.discard', $prospectA->id), ['reason' => 'test'])->assertNotFound();
});

it('never lists another tenant\'s LeadGreen prospects in the DataGrid', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'LeadGreen Grid A', 'code' => 'leadgreen-grid-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'LeadGreen Grid B', 'code' => 'leadgreen-grid-b-'.uniqid(), 'is_active' => true]);

    $userB = makeLeakageTestTenantUser($tenantB->id);

    $prospectA = LeadGreen::create(['tenant_id' => $tenantA->id, 'name' => 'Grid Tenant A Prospect', 'website' => 'https://grid-a.example']);
    $prospectB = LeadGreen::create(['tenant_id' => $tenantB->id, 'name' => 'Grid Tenant B Prospect', 'website' => 'https://grid-b.example']);

    $response = test()->actingAs($userB)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.leadgreen.index'));

    $response->assertOk();

    $ids = collect($response->json('records'))->pluck('id')->all();

    expect($ids)->toContain($prospectB->id)
        ->and($ids)->not->toContain($prospectA->id);
});

it('refuses a tenant-scoped user enriching another tenant\'s lead via the enrichment endpoint', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Enrich Leak A', 'code' => 'enrich-leak-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Enrich Leak B', 'code' => 'enrich-leak-b-'.uniqid(), 'is_active' => true]);

    $userB = makeLeakageTestTenantUser($tenantB->id);

    $pipelineA = makeLeakageTestPipeline($tenantA->id);
    $leadA = makeLeakageTestLead('Tenant A Lead for Enrichment', $pipelineA->id);

    test()->actingAs($userB)
        ->post(route('admin.leads.enrich', $leadA->id))
        ->assertNotFound();
});

it('stamps a public web form submission with the form\'s own tenant, not NULL', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'WebForm Leak Tenant', 'code' => 'webform-leak-'.uniqid(), 'is_active' => true]);

    $webForm = WebForm::create([
        'tenant_id' => $tenant->id,
        'form_id' => 'wf-'.uniqid(),
        'title' => 'Test Form',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thanks!',
        'create_lead' => false,
    ]);

    // A public, unauthenticated submission — no acting user at all.
    $response = test()->post(route('admin.settings.web_forms.form_store', $webForm->id), [
        'persons' => [
            'name' => 'Public Form Submitter',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
        ],
    ]);

    $response->assertOk();

    $person = Person::where('name', 'Public Form Submitter')->first();

    expect($person)->not->toBeNull()
        ->and($person->tenant_id)->toBe($tenant->id);
});

it('refuses a tenant-scoped user previewing another tenant\'s web form', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'WebForm View Leak A', 'code' => 'webform-view-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'WebForm View Leak B', 'code' => 'webform-view-b-'.uniqid(), 'is_active' => true]);

    $userB = makeLeakageTestTenantUser($tenantB->id);

    $webFormA = WebForm::create([
        'tenant_id' => $tenantA->id,
        'form_id' => 'wf-'.uniqid(),
        'title' => 'Tenant A Form',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thanks!',
        'create_lead' => false,
    ]);

    test()->actingAs($userB)
        ->get(route('admin.settings.web_forms.view', $webFormA->id))
        ->assertNotFound();
});

it('never shows another tenant\'s emails on a lead\'s activity timeline', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Activity Leak A', 'code' => 'activity-leak-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Activity Leak B', 'code' => 'activity-leak-b-'.uniqid(), 'is_active' => true]);

    $pipelineA = makeLeakageTestPipeline($tenantA->id);
    $pipelineB = makeLeakageTestPipeline($tenantB->id);

    $leadA = makeLeakageTestLead('Tenant A Lead for Activities', $pipelineA->id);
    $leadB = makeLeakageTestLead('Tenant B Lead for Activities', $pipelineB->id);

    Email::create([
        'tenant_id' => $tenantA->id,
        'lead_id' => $leadA->id,
        'subject' => 'Tenant A Secret Email',
        'reply' => 'This belongs to Tenant A only.',
        'folders' => json_encode(['inbox']),
        'from' => json_encode(['a@tenant-a.example']),
    ]);

    $userB = makeLeakageTestTenantUser($tenantB->id);

    // Tenant B directly requests Tenant A's own lead id.
    $response = test()->actingAs($userB)->get(route('admin.leads.activities.index', $leadA->id));

    $response->assertOk();

    $subjects = collect($response->json('data'))->pluck('title')->all();

    expect($subjects)->not->toContain('Tenant A Secret Email');
});

it('applies the data-scope filter on the Organizations grid instead of skipping it entirely', function () {
    // Regression test for dead code found auditing this grid for Phase
    // 2.6 — an early `return` made the view_permission filter and both
    // addFilter() calls unreachable, so every user saw every
    // organization regardless of their own data-scope restriction. A
    // Builder instance alone doesn't prove this — the old, dead-code
    // version also returned one — so this asserts the actual SQL carries
    // the restriction for a user scoped to only their own records.
    $admin = getDefaultAdmin();

    $restrictedUser = User::create([
        'name' => 'Individual Scope User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $admin->role_id,
        'view_permission' => 'individual',
    ]);

    test()->actingAs($restrictedUser);

    $dataGrid = app(OrganizationDataGrid::class);

    $queryBuilder = $dataGrid->prepareQueryBuilder();

    expect($queryBuilder->toSql())->toContain('`organizations`.`user_id`');
});
