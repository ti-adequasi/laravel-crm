<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Models\File;
use Webkul\Core\Models\CoreConfig;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\Tenant\Support\CurrentTenant;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function makeStorageTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Storage Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Storage Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

it('prefixes a storage path with the current tenant, and leaves it bare for the super-admin', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Storage Tenant', 'code' => 'storage-tenant-'.uniqid(), 'is_active' => true]);

    app()->instance('currentTenantId', $tenant->id);
    expect(CurrentTenant::scopedStoragePath('activities/1'))->toBe("tenants/{$tenant->id}/activities/1");

    app()->instance('currentTenantId', null);
    expect(CurrentTenant::scopedStoragePath('activities/1'))->toBe('activities/1');
});

it('refuses a tenant\'s config-file download to another tenant, but allows the super-admin and the tenant\'s own global fallback', function () {
    Storage::fake('public');

    $tenantA = app(TenantRepository::class)->create(['name' => 'Storage Tenant A', 'code' => 'storage-tenant-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Storage Tenant B', 'code' => 'storage-tenant-b-'.uniqid(), 'is_active' => true]);

    $userA = makeStorageTestTenantUser($tenantA->id);
    $userB = makeStorageTestTenantUser($tenantB->id);
    $superAdmin = getDefaultAdmin();

    // Tenant A's own override, stored at the new tenant-prefixed path a
    // real upload would now produce.
    CoreConfig::create([
        'tenant_id' => $tenantA->id,
        'code' => 'general.some.logo',
        'value' => "tenants/{$tenantA->id}/configuration/tenant-a-logo.png",
    ]);
    Storage::disk('public')->put("tenants/{$tenantA->id}/configuration/tenant-a-logo.png", 'tenant a logo bytes');

    // The pre-existing global row, at the old, unprefixed shape — proving
    // nothing already on disk needs to move for this to keep working.
    CoreConfig::create([
        'tenant_id' => null,
        'code' => 'general.some.other-logo',
        'value' => 'configuration/global-logo.png',
    ]);
    Storage::disk('public')->put('configuration/global-logo.png', 'global logo bytes');

    // Tenant A can download its own file.
    test()->actingAs($userA)
        ->get(route('admin.configuration.download', ['general', 'some', 'tenant-a-logo.png']))
        ->assertOk();

    // Tenant A can also reach the pre-existing global file (the same
    // fallback getConfigData() gives it on the read side).
    test()->actingAs($userA)
        ->get(route('admin.configuration.download', ['general', 'some', 'global-logo.png']))
        ->assertOk();

    // Tenant B must never reach Tenant A's own file.
    test()->actingAs($userB)
        ->get(route('admin.configuration.download', ['general', 'some', 'tenant-a-logo.png']))
        ->assertNotFound();

    // The super-admin can still reach every tenant's file, unfiltered.
    test()->actingAs($superAdmin)
        ->get(route('admin.configuration.download', ['general', 'some', 'tenant-a-logo.png']))
        ->assertOk();
});

it('scopes an activity file upload\'s stored path to the uploading user\'s tenant', function () {
    Storage::fake('public');

    $tenant = app(TenantRepository::class)->create(['name' => 'Activity Storage Tenant', 'code' => 'activity-storage-'.uniqid(), 'is_active' => true]);
    $user = makeStorageTestTenantUser($tenant->id);

    $response = test()->actingAs($user)->post(route('admin.activities.store'), [
        'type' => 'file',
        'file' => UploadedFile::fake()->create('note.pdf', 10),
    ]);

    $response->assertRedirect();

    $file = File::latest('id')->first();

    expect($file)->not->toBeNull()
        ->and($file->path)->toStartWith("tenants/{$tenant->id}/activities/");
});
