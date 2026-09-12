<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Pbx\Models\PbxSetting;
use Webkul\Pbx\Services\PbxClient;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function makeExtensionTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Extension Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Extension Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

it('accepts any extension when the tenant has no PBX configured', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Ext No Pbx Tenant', 'code' => 'ext-no-pbx-'.uniqid(), 'is_active' => true]);
    $user = makeExtensionTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->put(route('admin.settings.users.update', $user->id), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'view_permission' => 'global',
            'extension' => '1234',
        ])
        ->assertOk();

    expect($user->fresh()->extension)->toBe('1234');
});

it('rejects an extension that does not exist in the tenant\'s own PBX directory', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Ext Invalid Tenant', 'code' => 'ext-invalid-'.uniqid(), 'is_active' => true]);
    $user = makeExtensionTestTenantUser($tenant->id);

    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'api_key' => 'sk-fake']);

    app()->instance(PbxClient::class, new class extends PbxClient
    {
        public function __construct() {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function users(): array
        {
            return ['total' => 1, 'items' => [['user_uuid' => 'u1', 'username' => 'agent1', 'extension' => '2001', 'enabled' => true, 'groups' => []]]];
        }
    });

    test()->actingAs($user)
        ->put(route('admin.settings.users.update', $user->id), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'view_permission' => 'global',
            'extension' => '9999',
        ])
        ->assertSessionHasErrors('extension');

    expect($user->fresh()->extension)->toBeNull();
});

it('accepts an extension that does exist in the tenant\'s own PBX directory', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Ext Valid Tenant', 'code' => 'ext-valid-'.uniqid(), 'is_active' => true]);
    $user = makeExtensionTestTenantUser($tenant->id);

    PbxSetting::create(['tenant_id' => $tenant->id, 'enabled' => true, 'api_key' => 'sk-fake']);

    app()->instance(PbxClient::class, new class extends PbxClient
    {
        public function __construct() {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function users(): array
        {
            return ['total' => 1, 'items' => [['user_uuid' => 'u1', 'username' => 'agent1', 'extension' => '2001', 'enabled' => true, 'groups' => []]]];
        }
    });

    test()->actingAs($user)
        ->put(route('admin.settings.users.update', $user->id), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'view_permission' => 'global',
            'extension' => '2001',
        ])
        ->assertOk();

    expect($user->fresh()->extension)->toBe('2001');
});

it('lets a non-administrator set their own extension when self-editing', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Ext Self Tenant', 'code' => 'ext-self-'.uniqid(), 'is_active' => true]);

    $role = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'Limited Role '.uniqid(),
        'permission_type' => 'custom',
        'permissions' => ['settings.user.users.edit'],
    ]);

    $user = User::create([
        'name' => 'Limited User',
        'email' => uniqid().'@example.com',
        'password' => getDefaultAdmin()->password,
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => 'individual',
    ]);
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    test()->actingAs($user)
        ->put(route('admin.settings.users.update', $user->id), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'view_permission' => 'individual',
            'extension' => '3001',
        ])
        ->assertOk();

    expect($user->fresh()->extension)->toBe('3001');
});
