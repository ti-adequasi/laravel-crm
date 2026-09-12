<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Pbx\Models\PbxSetting;
use Webkul\Pbx\Services\PbxClient;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function makeHistoryTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'History Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'History Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

function makeHistoryTestPerson(int $tenantId, array $contactNumbers)
{
    $person = app(PersonRepository::class)->create([
        'name' => 'History Test Person',
        'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
        'contact_numbers' => $contactNumbers,
        'entity_type' => 'persons',
    ]);

    $person->forceFill(['tenant_id' => $tenantId])->save();

    return $person->fresh();
}

function enableHistoryPbxForTenant(int $tenantId): void
{
    PbxSetting::create(['tenant_id' => $tenantId, 'enabled' => true, 'auto_log_activity' => true, 'api_key' => 'sk-fake-'.uniqid()]);
}

/**
 * A configurable PbxClient stand-in for history endpoints — callsByNumber
 * maps a normalized number to the raw `items` array /v1/calls would return
 * for it, so a test can prove numbers are queried separately and merged.
 */
function mockHistoryPbxClient(array $callsByNumber = [], ?array $recordingUrlResponse = null, ?array $intelResponse = null): PbxClient
{
    return new class($callsByNumber, $recordingUrlResponse, $intelResponse) extends PbxClient
    {
        public function __construct(private array $callsByNumber, private ?array $recordingUrlResponse, private ?array $intelResponse) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function calls(array $filters = []): array
        {
            return ['items' => $this->callsByNumber[$filters['number']] ?? []];
        }

        public function recordingSignedUrl(string $xmlCdrUuid): array
        {
            return $this->recordingUrlResponse ?? [];
        }

        public function intel(string $xmlCdrUuid): array
        {
            return $this->intelResponse ?? ['xml_cdr_uuid' => $xmlCdrUuid, 'modules' => []];
        }

        public function analyze(string $xmlCdrUuid, bool $force = false): array
        {
            return ['xml_cdr_uuid' => $xmlCdrUuid, 'status' => 'queued'];
        }
    };
}

it('refuses when the tenant has no PBX configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History No Pbx Tenant', 'code' => 'history-no-pbx-'.uniqid(), 'is_active' => true]);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, [['value' => '11987654321', 'label' => 'mobile']]);

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertStatus(422);
});

it('404s for a person that does not exist', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History No Person Tenant', 'code' => 'history-no-person-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);

    app()->instance(PbxClient::class, mockHistoryPbxClient());

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => 999999]))
        ->assertStatus(404);
});

it('merges and de-duplicates call history across a person\'s multiple numbers, newest first', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Merge Tenant', 'code' => 'history-merge-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, [
        ['value' => '11987654321', 'label' => 'mobile'],
        ['value' => '1132654321', 'label' => 'work'],
    ]);

    app()->instance(PbxClient::class, mockHistoryPbxClient(callsByNumber: [
        '11987654321' => [
            ['xml_cdr_uuid' => 'call-1', 'start_stamp' => '2026-09-10T10:00:00', 'destination_number' => '11987654321'],
            ['xml_cdr_uuid' => 'call-shared', 'start_stamp' => '2026-09-11T09:00:00', 'destination_number' => '11987654321'],
        ],
        '1132654321' => [
            ['xml_cdr_uuid' => 'call-2', 'start_stamp' => '2026-09-12T08:00:00', 'destination_number' => '1132654321'],
            // Same CDR entry surfacing under both numbers shouldn't happen in
            // practice, but proves de-duplication rather than assuming it.
            ['xml_cdr_uuid' => 'call-shared', 'start_stamp' => '2026-09-11T09:00:00', 'destination_number' => '11987654321'],
        ],
    ]));

    $response = test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertOk();

    $uuids = collect($response->json('calls'))->pluck('xml_cdr_uuid');

    expect($uuids)->toHaveCount(3)
        ->and($uuids->all())->toBe(['call-2', 'call-shared', 'call-1']);
});

it('skips a contact number that cannot be normalized rather than failing the whole request', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Bad Number Tenant', 'code' => 'history-bad-number-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, [
        ['value' => 'not-a-real-number', 'label' => 'bad'],
        ['value' => '11987654321', 'label' => 'mobile'],
    ]);

    app()->instance(PbxClient::class, mockHistoryPbxClient(callsByNumber: [
        '11987654321' => [['xml_cdr_uuid' => 'call-ok', 'start_stamp' => '2026-09-12T08:00:00']],
    ]));

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertOk()
        ->assertJson(['calls' => [['xml_cdr_uuid' => 'call-ok', 'start_stamp' => '2026-09-12T08:00:00']]]);
});

it('still queries a number too incomplete to fully normalize, confirmed against the real PBX to matter', function () {
    // A real call on the live PBX was recorded against "965963302" — 9
    // digits, no DDD, which PhoneNumberNormalizer correctly refuses as an
    // incomplete national number (it exists to keep Phase 3 from ever
    // dialing something malformed). This lookup is read-only, so it falls
    // back to the plain digit string instead of skipping the number
    // entirely — confirmed necessary, not just theoretical, once a real
    // API key made this real data visible.
    $tenant = app(TenantRepository::class)->create(['name' => 'History Loose Match Tenant', 'code' => 'history-loose-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, [
        ['value' => '965963302', 'label' => 'work'],
    ]);

    app()->instance(PbxClient::class, mockHistoryPbxClient(callsByNumber: [
        '965963302' => [['xml_cdr_uuid' => 'call-loose-match', 'start_stamp' => '2026-09-11T17:43:29']],
    ]));

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertOk()
        ->assertJson(['calls' => [['xml_cdr_uuid' => 'call-loose-match', 'start_stamp' => '2026-09-11T17:43:29']]]);
});

it('still excludes a number with fewer than 6 digits even as a loose match', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Too Short Tenant', 'code' => 'history-too-short-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, [
        ['value' => '1234', 'label' => 'work'],
    ]);

    app()->instance(PbxClient::class, mockHistoryPbxClient());

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertOk()
        ->assertJson(['calls' => []]);
});

it('returns an empty list rather than erroring when the person has no usable numbers at all', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History No Numbers Tenant', 'code' => 'history-no-numbers-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);
    $person = makeHistoryTestPerson($tenant->id, []);

    app()->instance(PbxClient::class, mockHistoryPbxClient());

    test()->actingAs($user)
        ->get(route('admin.pbx.history.index', ['person_id' => $person->id]))
        ->assertOk()
        ->assertJson(['calls' => []]);
});

it('returns a recording signed url', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Recording Tenant', 'code' => 'history-recording-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);

    app()->instance(PbxClient::class, mockHistoryPbxClient(recordingUrlResponse: ['url' => 'https://pbx.example/rec/signed-abc']));

    test()->actingAs($user)
        ->get(route('admin.pbx.history.recording-url', 'some-cdr-uuid'))
        ->assertOk()
        ->assertJson(['url' => 'https://pbx.example/rec/signed-abc']);
});

it('reports a clean failure when the recording url response has no recognizable field', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Recording Bad Tenant', 'code' => 'history-recording-bad-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);

    app()->instance(PbxClient::class, mockHistoryPbxClient(recordingUrlResponse: ['something_unexpected' => true]));

    test()->actingAs($user)
        ->get(route('admin.pbx.history.recording-url', 'some-cdr-uuid'))
        ->assertStatus(502);
});

it('returns the AI intel modules already recorded for a call', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Intel Tenant', 'code' => 'history-intel-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);

    $intelResponse = [
        'xml_cdr_uuid' => 'some-cdr-uuid',
        'modules' => [
            'transcript' => ['analysis_type' => 'transcript', 'status' => 'done', 'result' => ['text' => 'Hello there.']],
            'qa' => ['analysis_type' => 'qa', 'status' => 'done', 'result' => ['resumo' => 'A short call.', 'nota' => 5, 'sentimento' => 'neutro']],
        ],
    ];

    app()->instance(PbxClient::class, mockHistoryPbxClient(intelResponse: $intelResponse));

    test()->actingAs($user)
        ->get(route('admin.pbx.history.intel', 'some-cdr-uuid'))
        ->assertOk()
        ->assertJson($intelResponse);
});

it('refuses to fetch intel when the tenant has no PBX configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Intel No Pbx Tenant', 'code' => 'history-intel-no-pbx-'.uniqid(), 'is_active' => true]);
    $user = makeHistoryTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->get(route('admin.pbx.history.intel', 'some-cdr-uuid'))
        ->assertStatus(422);
});

it('starts an analysis and returns a confirmation message', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Analyze Tenant', 'code' => 'history-analyze-'.uniqid(), 'is_active' => true]);
    enableHistoryPbxForTenant($tenant->id);
    $user = makeHistoryTestTenantUser($tenant->id);

    app()->instance(PbxClient::class, mockHistoryPbxClient());

    test()->actingAs($user)
        ->post(route('admin.pbx.history.analyze', 'some-cdr-uuid'))
        ->assertOk()
        ->assertJsonStructure(['message']);
});

it('refuses to start an analysis when the tenant has no PBX configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'History Analyze No Pbx Tenant', 'code' => 'history-analyze-no-pbx-'.uniqid(), 'is_active' => true]);
    $user = makeHistoryTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->post(route('admin.pbx.history.analyze', 'some-cdr-uuid'))
        ->assertStatus(422);
});
