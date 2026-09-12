<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Pbx\Exceptions\PbxNotConfiguredException;
use Webkul\Pbx\Models\PbxSetting;
use Webkul\Pbx\Services\PbxClient;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function makePbxTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Pbx Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Pbx Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

it('saves enabled and api_key for the current tenant, encrypted at rest', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Pbx Save Tenant', 'code' => 'pbx-save-'.uniqid(), 'is_active' => true]);
    $user = makePbxTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->put(route('admin.pbx.update'), ['enabled' => true, 'api_key' => 'sk-real-test-key'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    $row = DB::table('pbx_settings')->where('tenant_id', $tenant->id)->first();

    expect($row->enabled)->toBe(1)
        // The raw column holds ciphertext, not the plain key.
        ->and($row->api_key)->not->toBe('sk-real-test-key');

    $setting = PbxSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($setting->api_key)->toBe('sk-real-test-key');
});

it('keeps the existing api_key when the update submits a blank one', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Pbx Blank Tenant', 'code' => 'pbx-blank-'.uniqid(), 'is_active' => true]);
    $user = makePbxTestTenantUser($tenant->id);

    test()->actingAs($user)->put(route('admin.pbx.update'), ['enabled' => true, 'api_key' => 'sk-original-key']);

    // A later save that flips `enabled` off, without re-typing the key —
    // exactly what the edit form does, since it never re-displays a saved
    // key for the user to accidentally overwrite with a blank field.
    test()->actingAs($user)->put(route('admin.pbx.update'), ['enabled' => false, 'api_key' => '']);

    $setting = PbxSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($setting->enabled)->toBeFalse()
        ->and($setting->api_key)->toBe('sk-original-key');
});

it('never lets one tenant\'s PbxClient read another tenant\'s api_key, and treats an unconfigured tenant as not configured rather than falling back', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Pbx Isolation A', 'code' => 'pbx-isolation-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Pbx Isolation B', 'code' => 'pbx-isolation-b-'.uniqid(), 'is_active' => true]);

    PbxSetting::create(['tenant_id' => $tenantA->id, 'enabled' => true, 'api_key' => 'sk-tenant-a-key']);
    // Tenant B has no row at all.

    app()->instance('currentTenantId', $tenantA->id);
    expect(app(PbxClient::class)->isConfigured())->toBeTrue();

    app()->instance('currentTenantId', $tenantB->id);
    expect(app(PbxClient::class)->isConfigured())->toBeFalse();

    expect(fn () => app(PbxClient::class)->me())
        ->toThrow(PbxNotConfiguredException::class);
});

it('refuses to test a connection with no key available at all', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Pbx No Key Tenant', 'code' => 'pbx-no-key-'.uniqid(), 'is_active' => true]);
    $user = makePbxTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->post(route('admin.pbx.test'), ['api_key' => ''])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('does not persist anything when only testing a connection', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Pbx Test Only Tenant', 'code' => 'pbx-test-only-'.uniqid(), 'is_active' => true]);
    $user = makePbxTestTenantUser($tenant->id);

    test()->actingAs($user)->post(route('admin.pbx.test'), ['api_key' => 'sk-never-saved']);

    expect(PbxSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});
