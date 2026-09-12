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

uses(DatabaseTransactions::class);

function makePbxCallTestTenantUser(int $tenantId, ?string $extension = '1001'): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Pbx Call Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Pbx Call Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
        'extension' => $extension,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

function makePbxCallTestLead(int $tenantId): Lead
{
    $lead = app(LeadRepository::class)->create([
        'title' => 'Pbx Call Test Lead '.uniqid(),
        'lead_value' => 100,
        'lead_pipeline_id' => 1,
        'lead_pipeline_stage_id' => 1,
        'status' => 1,
        'person' => [
            'name' => 'Pbx Call Test Person',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
            'entity_type' => 'persons',
        ],
        'entity_type' => 'leads',
    ]);

    $lead->forceFill(['tenant_id' => $tenantId])->save();
    $lead->person->forceFill(['tenant_id' => $tenantId])->save();

    return $lead->fresh();
}

function enablePbxForTenant(int $tenantId): void
{
    PbxSetting::create(['tenant_id' => $tenantId, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-'.uniqid()]);
}

/**
 * A configurable PbxClient stand-in — canned responses for originate()/
 * callStatus(), everything else delegates to the real PbxNotConfiguredException-
 * free defaults these tests don't otherwise exercise.
 */
function mockPbxClient(array $originateResponse = ['call_uuid' => 'uuid-fake-call'], array $statusResponse = ['status' => 'ringing']): PbxClient
{
    return new class($originateResponse, $statusResponse) extends PbxClient
    {
        public function __construct(private array $originateResponse, private array $statusResponse) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function originate(string $ramal, string $telefone): array
        {
            return $this->originateResponse;
        }

        public function callStatus(string $callUuid): array
        {
            return $this->statusResponse;
        }

        public function hangup(string $callUuid): array
        {
            return ['status' => 'ended'];
        }
    };
}

it('refuses to originate when the tenant has no PBX configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call No Pbx Tenant', 'code' => 'call-no-pbx-'.uniqid(), 'is_active' => true]);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    test()->actingAs($user)
        ->post(route('admin.pbx.calls.originate'), ['lead_id' => $lead->id, 'person_id' => $lead->person->id, 'phone' => '11987654321'])
        ->assertStatus(422);

    expect(PbxCall::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to originate when the acting user has no extension configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call No Ext Tenant', 'code' => 'call-no-ext-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id, extension: null);
    $lead = makePbxCallTestLead($tenant->id);

    app()->instance(PbxClient::class, mockPbxClient());

    test()->actingAs($user)
        ->post(route('admin.pbx.calls.originate'), ['lead_id' => $lead->id, 'person_id' => $lead->person->id, 'phone' => '11987654321'])
        ->assertStatus(422);
});

it('rejects a phone number that cannot be normalized', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Bad Phone Tenant', 'code' => 'call-bad-phone-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    app()->instance(PbxClient::class, mockPbxClient());

    test()->actingAs($user)
        ->post(route('admin.pbx.calls.originate'), ['lead_id' => $lead->id, 'person_id' => $lead->person->id, 'phone' => 'not-a-phone'])
        ->assertStatus(422);

    expect(PbxCall::withoutGlobalScopes()->count())->toBe(0);
});

it('originates a call and persists it with the PBX-assigned call_uuid', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Happy Tenant', 'code' => 'call-happy-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id, extension: '2002');
    $lead = makePbxCallTestLead($tenant->id);

    app()->instance(PbxClient::class, mockPbxClient(originateResponse: ['call_uuid' => 'uuid-happy-path']));

    $response = test()->actingAs($user)
        ->post(route('admin.pbx.calls.originate'), ['lead_id' => $lead->id, 'person_id' => $lead->person->id, 'phone' => '(11) 98765-4321'])
        ->assertOk()
        ->assertJsonStructure(['call_uuid', 'status_url', 'hangup_url']);

    expect($response->json('call_uuid'))->toBe('uuid-happy-path');

    $call = PbxCall::withoutGlobalScopes()->where('call_uuid', 'uuid-happy-path')->first();

    expect($call)->not->toBeNull()
        ->and($call->tenant_id)->toBe($tenant->id)
        ->and($call->initiated_by_user_id)->toBe($user->id)
        ->and($call->lead_id)->toBe($lead->id)
        ->and($call->person_id)->toBe($lead->person->id)
        ->and($call->ramal)->toBe('2002')
        ->and($call->telefone)->toBe('11987654321')
        ->and($call->telefone_raw)->toBe('(11) 98765-4321')
        ->and($call->direction)->toBe('outbound')
        ->and($call->hasEnded())->toBeFalse();
});

it('404s a status request for a call_uuid that does not exist', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call 404 Tenant', 'code' => 'call-404-'.uniqid(), 'is_active' => true]);
    $user = makePbxCallTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->get(route('admin.pbx.calls.status', 'no-such-uuid'))
        ->assertStatus(404);
});

it('403s a status request for a call initiated by a different user in the same tenant', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Cross User Tenant', 'code' => 'call-cross-user-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $owner = makePbxCallTestTenantUser($tenant->id, extension: '3001');
    $intruder = makePbxCallTestTenantUser($tenant->id, extension: '3002');
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-owned-by-owner',
        'initiated_by_user_id' => $owner->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '3001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    test()->actingAs($intruder)
        ->get(route('admin.pbx.calls.status', $call->call_uuid))
        ->assertStatus(403);

    test()->actingAs($intruder)
        ->delete(route('admin.pbx.calls.hangup', $call->call_uuid))
        ->assertStatus(403);
});

it('reports ended:false while the PBX still reports the call as active', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Active Tenant', 'code' => 'call-active-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-still-active',
        'initiated_by_user_id' => $user->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '1001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    app()->instance(PbxClient::class, mockPbxClient(statusResponse: ['status' => 'answered']));

    test()->actingAs($user)
        ->get(route('admin.pbx.calls.status', $call->call_uuid))
        ->assertOk()
        ->assertJson(['ended' => false]);

    expect($call->fresh()->hasEnded())->toBeFalse();
});

it('finalizes the call and auto-logs an Activity once the PBX reports a terminal status', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Ends Tenant', 'code' => 'call-ends-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id, extension: '4001');
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-will-end',
        'initiated_by_user_id' => $user->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '4001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    app()->instance(PbxClient::class, mockPbxClient(statusResponse: ['status' => 'completed', 'duration' => 42]));

    test()->actingAs($user)
        ->get(route('admin.pbx.calls.status', $call->call_uuid))
        ->assertOk()
        ->assertJson(['ended' => true]);

    $call = $call->fresh();

    expect($call->hasEnded())->toBeTrue()
        ->and($call->activity_logged_at)->not->toBeNull();

    // Scoped to this test's own lead, not a bare type='call' count — this
    // suite shares its database with the live dev app (no isolated test
    // DB configured), so an unscoped query can pick up unrelated 'call'
    // Activities that happen to already exist there.
    $activity = Activity::whereHas('leads', fn ($q) => $q->where('leads.id', $lead->id))->where('type', 'call')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->is_done)->toBeTruthy()
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->leads->pluck('id'))->toContain($lead->id)
        ->and($activity->persons->pluck('id'))->toContain($lead->person->id);

    $additional = json_decode($activity->additional, true);

    expect($additional['call_uuid'])->toBe('uuid-will-end')
        ->and($additional['duration_seconds'])->toBe(42);
});

it('does not double-log the Activity on a second poll after the call already ended', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call No Double Log Tenant', 'code' => 'call-no-double-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-no-double-log',
        'initiated_by_user_id' => $user->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '1001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    app()->instance(PbxClient::class, mockPbxClient(statusResponse: ['status' => 'completed']));

    test()->actingAs($user)->get(route('admin.pbx.calls.status', $call->call_uuid))->assertOk();
    test()->actingAs($user)->get(route('admin.pbx.calls.status', $call->call_uuid))->assertOk();

    expect(Activity::whereHas('leads', fn ($q) => $q->where('leads.id', $lead->id))->where('type', 'call')->count())->toBe(1);
});

it('does not create an Activity when auto_log_activity is off, but still marks the call as handled', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call No Autolog Tenant', 'code' => 'call-no-autolog-'.uniqid(), 'is_active' => true]);
    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'auto_log_activity' => false, 'api_key' => 'sk-fake-'.uniqid()]);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-no-autolog',
        'initiated_by_user_id' => $user->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '1001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    app()->instance(PbxClient::class, mockPbxClient(statusResponse: ['status' => 'completed']));

    test()->actingAs($user)->get(route('admin.pbx.calls.status', $call->call_uuid))->assertOk();

    expect($call->fresh()->activity_logged_at)->not->toBeNull()
        ->and(Activity::whereHas('leads', fn ($q) => $q->where('leads.id', $lead->id))->where('type', 'call')->count())->toBe(0);
});

it('lets hangup finalize an in-progress call', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Call Hangup Tenant', 'code' => 'call-hangup-'.uniqid(), 'is_active' => true]);
    enablePbxForTenant($tenant->id);
    $user = makePbxCallTestTenantUser($tenant->id);
    $lead = makePbxCallTestLead($tenant->id);

    $call = PbxCall::create([
        'tenant_id' => $tenant->id,
        'call_uuid' => 'uuid-hangup-me',
        'initiated_by_user_id' => $user->id,
        'lead_id' => $lead->id,
        'person_id' => $lead->person->id,
        'ramal' => '1001',
        'telefone_raw' => '11987654321',
        'telefone' => '11987654321',
        'status' => 'ringing',
    ]);

    app()->instance(PbxClient::class, mockPbxClient(statusResponse: ['status' => 'ended']));

    test()->actingAs($user)
        ->delete(route('admin.pbx.calls.hangup', $call->call_uuid))
        ->assertOk()
        ->assertJson(['ended' => true]);

    expect($call->fresh()->hasEnded())->toBeTrue();
});
