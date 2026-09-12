<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Pbx\Models\PbxCall;
use Webkul\Pbx\Models\PbxSetting;
use Webkul\Pbx\Services\PbxClient;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

// Self-contained on purpose, even at the cost of duplicating PbxCallTest's
// own near-identical helpers under different names — Pest requires every
// test file up front (so the global functions all end up defined
// regardless of which file happens to run first), but two files sharing
// one helper by name is exactly the kind of implicit cross-file coupling
// the rest of this suite's own convention (uniquely-named-per-file
// helpers) avoids.
uses(DatabaseTransactions::class);

function makeReconcileTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Reconcile Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Reconcile Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
        'extension' => '1001',
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

function makeReconcileTestLead(int $tenantId): Lead
{
    $lead = app(LeadRepository::class)->create([
        'title' => 'Reconcile Test Lead '.uniqid(),
        'lead_value' => 100,
        'lead_pipeline_id' => 1,
        'lead_pipeline_stage_id' => 1,
        'status' => 1,
        'person' => [
            'name' => 'Reconcile Test Person',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
            'entity_type' => 'persons',
        ],
        'entity_type' => 'leads',
    ]);

    $lead->forceFill(['tenant_id' => $tenantId])->save();
    $lead->person->forceFill(['tenant_id' => $tenantId])->save();

    return $lead->fresh();
}

function makeStuckPbxCall(int $tenantId, string $callUuid, int $userId, int $leadId, int $personId, DateTimeInterface $createdAt): PbxCall
{
    $call = PbxCall::create([
        'tenant_id' => $tenantId,
        'call_uuid' => $callUuid,
        'initiated_by_user_id' => $userId,
        'lead_id' => $leadId,
        'person_id' => $personId,
        'ramal' => '1001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    // created_at has no mass-assignment path through the model's own
    // $fillable (deliberately — nothing else should be able to backdate
    // it), so it's set directly here to simulate a call that's actually
    // old enough for the command's --older-than cutoff to pick up.
    $call->timestamps = false;
    $call->created_at = $createdAt;
    $call->save();

    return $call->fresh();
}

function mockReconcilePbxClient(array $statusResponse): PbxClient
{
    return new class($statusResponse) extends PbxClient
    {
        public function __construct(private array $statusResponse) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function callStatus(string $callUuid): array
        {
            return $this->statusResponse;
        }
    };
}

it('finalizes a stuck call older than the cutoff and logs its Activity', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Reconcile Tenant', 'code' => 'reconcile-'.uniqid(), 'is_active' => true]);
    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-'.uniqid()]);
    $user = makeReconcileTestTenantUser($tenant->id);
    $lead = makeReconcileTestLead($tenant->id);

    $call = makeStuckPbxCall($tenant->id, 'uuid-stuck-old', $user->id, $lead->id, $lead->person->id, now()->subMinutes(30));

    app()->instance(PbxClient::class, mockReconcilePbxClient(['status' => 'completed']));

    test()->artisan('pbx:reconcile-calls', ['--older-than' => 10])
        ->assertSuccessful();

    expect($call->fresh()->hasEnded())->toBeTrue()
        ->and(Activity::whereHas('leads', fn ($q) => $q->where('leads.id', $lead->id))->where('type', 'call')->count())->toBe(1);
});

it('leaves a call younger than the cutoff alone', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Reconcile Young Tenant', 'code' => 'reconcile-young-'.uniqid(), 'is_active' => true]);
    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-'.uniqid()]);
    $user = makeReconcileTestTenantUser($tenant->id);
    $lead = makeReconcileTestLead($tenant->id);

    $call = makeStuckPbxCall($tenant->id, 'uuid-stuck-young', $user->id, $lead->id, $lead->person->id, now()->subMinutes(2));

    app()->instance(PbxClient::class, mockReconcilePbxClient(['status' => 'completed']));

    test()->artisan('pbx:reconcile-calls', ['--older-than' => 10])->assertSuccessful();

    expect($call->fresh()->hasEnded())->toBeFalse();
});

it('leaves a genuinely still-active call alone even past the cutoff', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Reconcile Active Tenant', 'code' => 'reconcile-active-'.uniqid(), 'is_active' => true]);
    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-'.uniqid()]);
    $user = makeReconcileTestTenantUser($tenant->id);
    $lead = makeReconcileTestLead($tenant->id);

    $call = makeStuckPbxCall($tenant->id, 'uuid-still-going', $user->id, $lead->id, $lead->person->id, now()->subMinutes(30));

    app()->instance(PbxClient::class, mockReconcilePbxClient(['status' => 'answered']));

    test()->artisan('pbx:reconcile-calls', ['--older-than' => 10])->assertSuccessful();

    expect($call->fresh()->hasEnded())->toBeFalse();
});

it('processes stuck calls across multiple tenants in one run, each finalized under its own tenant', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Reconcile Multi A', 'code' => 'reconcile-multi-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Reconcile Multi B', 'code' => 'reconcile-multi-b-'.uniqid(), 'is_active' => true]);
    PbxSetting::create(['tenant_id' => $tenantA->id, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-a-'.uniqid()]);
    PbxSetting::create(['tenant_id' => $tenantB->id, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-b-'.uniqid()]);

    $userA = makeReconcileTestTenantUser($tenantA->id);
    $userB = makeReconcileTestTenantUser($tenantB->id);
    $leadA = makeReconcileTestLead($tenantA->id);
    $leadB = makeReconcileTestLead($tenantB->id);

    $callA = makeStuckPbxCall($tenantA->id, 'uuid-multi-a', $userA->id, $leadA->id, $leadA->person->id, now()->subMinutes(30));
    $callB = makeStuckPbxCall($tenantB->id, 'uuid-multi-b', $userB->id, $leadB->id, $leadB->person->id, now()->subMinutes(30));

    app()->instance(PbxClient::class, mockReconcilePbxClient(['status' => 'completed']));

    test()->artisan('pbx:reconcile-calls', ['--older-than' => 10])->assertSuccessful();

    expect($callA->fresh()->hasEnded())->toBeTrue()
        ->and($callB->fresh()->hasEnded())->toBeTrue();

    $activities = Activity::withoutGlobalScopes()
        ->whereHas('leads', fn ($q) => $q->whereIn('leads.id', [$leadA->id, $leadB->id]))
        ->where('type', 'call')
        ->get();

    expect($activities->pluck('tenant_id')->sort()->values()->all())->toBe([$tenantA->id, $tenantB->id]);
});
