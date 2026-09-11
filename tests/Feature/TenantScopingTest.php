<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * A brand-new tenant genuinely needs its own pipeline (with at least one
 * stage) — the pre-existing pipeline (id=1) has tenant_id NULL and is
 * therefore invisible to a tenant-scoped user once BelongsToTenant is
 * active, exactly like every other tenant-owned row. Real tenant
 * onboarding/provisioning is a separate later concern; this just gives a
 * test tenant what it actually needs to exercise the Leads screens at all.
 */
function createTenantScopingTestPipeline(int $tenantId): Pipeline
{
    $pipeline = Pipeline::create([
        'tenant_id' => $tenantId,
        'name' => 'Test Pipeline '.uniqid(),
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

function createTenantScopingTestLead(string $title, int $pipelineId = 1): Lead
{
    return app(LeadRepository::class)->create([
        'title' => $title,
        'lead_value' => 100,
        'lead_pipeline_id' => $pipelineId,
        'lead_pipeline_stage_id' => Pipeline::find($pipelineId)->stages->first()->id,
        'status' => 1,
        'person' => [
            'name' => 'Tenant Scoping Test Person',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
            'entity_type' => 'persons',
        ],
        'entity_type' => 'leads',
    ]);
}

/**
 * Role is tenant-owned too — the pre-existing roles all have tenant_id
 * NULL, same as the pre-existing pipeline, and are therefore invisible to
 * a tenant-scoped user's own role lookup (Bouncer resolves $user->role,
 * which is itself scoped). A real tenant needs its own role for the exact
 * same reason it needs its own pipeline; this gives the test one with
 * permission_type 'all' so ACL itself is a non-factor in these tests,
 * which are about data scoping, not permission restriction.
 */
function createTenantScopingTestRole(int $tenantId): Role
{
    return Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);
}

function makeTenantScopedTestUser(?int $tenantId): User
{
    $admin = getDefaultAdmin();

    $roleId = $tenantId === null ? $admin->role_id : createTenantScopingTestRole($tenantId)->id;

    // A real, distinct User row — cloning the default admin's password so
    // the only thing under test is tenant_id, not some unrelated
    // credential difference.
    $user = User::create([
        'name' => 'Tenant Test User '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $roleId,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

/**
 * `test()->actingAs($user)` alone only sets the authenticated user for the
 * test — it does NOT run the real HTTP middleware stack, so
 * Webkul\Tenant\Http\Middleware\ResolveTenant (which is what actually binds
 * CurrentTenant in production) never fires unless the test also makes a
 * real `get()`/`post()` call. Tests that assert Eloquent-level scoping
 * directly (no HTTP round-trip) need to bind it themselves to accurately
 * simulate what a real request would have already done by this point.
 */
function actingAsTenantUser(User $user): void
{
    test()->actingAs($user);

    app()->instance('currentTenantId', $user->tenant_id);
}

it('only shows a tenant-scoped user their own tenant\'s leads via Eloquent', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Tenant A', 'code' => 'tenant-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Tenant B', 'code' => 'tenant-b-'.uniqid(), 'is_active' => true]);

    $pipelineA = createTenantScopingTestPipeline($tenantA->id);
    $pipelineB = createTenantScopingTestPipeline($tenantB->id);

    $userA = makeTenantScopedTestUser($tenantA->id);

    actingAsTenantUser($userA);
    $leadA = createTenantScopingTestLead('Lead for A', $pipelineA->id);

    // Switch the bound tenant directly to simulate Tenant B's own request
    // creating their lead, independent of whichever user object is
    // "acting" — only CurrentTenant should determine the stamped tenant_id.
    app()->instance('currentTenantId', $tenantB->id);
    $leadB = createTenantScopingTestLead('Lead for B', $pipelineB->id);

    actingAsTenantUser($userA);

    $visibleIds = Lead::pluck('id')->all();

    expect($visibleIds)->toContain($leadA->id)
        ->and($visibleIds)->not->toContain($leadB->id);
});

it('auto-stamps tenant_id when a tenant-scoped user creates a lead', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Tenant C', 'code' => 'tenant-c-'.uniqid(), 'is_active' => true]);
    $pipeline = createTenantScopingTestPipeline($tenant->id);
    $user = makeTenantScopedTestUser($tenant->id);

    actingAsTenantUser($user);

    $lead = createTenantScopingTestLead('Auto-stamped Lead', $pipeline->id);

    expect($lead->tenant_id)->toBe($tenant->id);
});

it('lets a super-admin see every tenant\'s leads unfiltered', function () {
    // Resolved before any tenant gets bound below — User is tenant-owned
    // too, so a bound tenant would make User::find() (inside
    // getDefaultAdmin()) invisible to its own lookup, same reason a
    // tenant-scoped user needs their own role.
    $superAdmin = getDefaultAdmin();
    expect($superAdmin->tenant_id)->toBeNull();

    $tenantA = app(TenantRepository::class)->create(['name' => 'Tenant D', 'code' => 'tenant-d-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Tenant E', 'code' => 'tenant-e-'.uniqid(), 'is_active' => true]);

    $pipelineA = createTenantScopingTestPipeline($tenantA->id);
    $pipelineB = createTenantScopingTestPipeline($tenantB->id);

    app()->instance('currentTenantId', $tenantA->id);
    $leadA = createTenantScopingTestLead('Lead for D', $pipelineA->id);

    app()->instance('currentTenantId', $tenantB->id);
    $leadB = createTenantScopingTestLead('Lead for E', $pipelineB->id);

    actingAsTenantUser($superAdmin);

    $visibleIds = Lead::pluck('id')->all();

    expect($visibleIds)->toContain($leadA->id)
        ->and($visibleIds)->toContain($leadB->id);
});

it('scopes the real Leads DataGrid AJAX response to the current tenant', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Tenant F', 'code' => 'tenant-f-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Tenant G', 'code' => 'tenant-g-'.uniqid(), 'is_active' => true]);

    $pipelineA = createTenantScopingTestPipeline($tenantA->id);
    $pipelineB = createTenantScopingTestPipeline($tenantB->id);

    $userA = makeTenantScopedTestUser($tenantA->id);

    app()->instance('currentTenantId', $tenantA->id);
    $leadA = createTenantScopingTestLead('DataGrid Lead A', $pipelineA->id);

    app()->instance('currentTenantId', $tenantB->id);
    $leadB = createTenantScopingTestLead('DataGrid Lead B', $pipelineB->id);

    // A real HTTP round-trip — ResolveTenant middleware actually runs here
    // and binds CurrentTenant from $userA itself, no manual binding needed.
    $response = test()->actingAs($userA)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.leads.index'));

    $response->assertOk();

    // The DataGrid's JSON envelope nests rows under "records", not "data"
    // (top-level keys are id/columns/actions/mass_actions/records/meta).
    $ids = collect($response->json('records'))->pluck('id')->all();

    expect($ids)->toContain($leadA->id)
        ->and($ids)->not->toContain($leadB->id);
});

it('404s a tenant-scoped user trying to view another tenant\'s lead directly by id', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Tenant H', 'code' => 'tenant-h-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Tenant I', 'code' => 'tenant-i-'.uniqid(), 'is_active' => true]);

    createTenantScopingTestPipeline($tenantA->id);
    $pipelineB = createTenantScopingTestPipeline($tenantB->id);

    $userA = makeTenantScopedTestUser($tenantA->id);

    app()->instance('currentTenantId', $tenantB->id);
    $leadB = createTenantScopingTestLead('Lead for I', $pipelineB->id);

    // Real HTTP round-trip again — ResolveTenant binds Tenant A from $userA.
    test()->actingAs($userA)
        ->get(route('admin.leads.view', $leadB->id))
        ->assertNotFound();
});

it('leaves pre-existing NULL-tenant data visible to a super-admin, unfiltered', function () {
    // The load-bearing backward-compatibility property behind Phase 2.2:
    // every row that predates this migration has tenant_id NULL, and a
    // super-admin (also tenant_id NULL) must keep seeing it exactly as
    // before — proven directly, not just inferred from the full suite
    // passing unchanged. Uses the real pre-existing pipeline (id=1),
    // itself tenant_id NULL, exactly like the legacy data it represents.
    app()->instance('currentTenantId', null);
    $lead = createTenantScopingTestLead('Legacy Lead', 1);

    expect($lead->tenant_id)->toBeNull();

    actingAsTenantUser(getDefaultAdmin());

    expect(Lead::pluck('id')->all())->toContain($lead->id);
});
