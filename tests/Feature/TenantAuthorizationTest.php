<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Lead\Models\Pipeline;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('lets a super-admin (tenant_id null) reach the tenants screen', function () {
    $admin = getDefaultAdmin();

    // Phase 2.1 is additive-only — no existing user has a tenant_id yet,
    // which is exactly what makes them a super-admin under this design.
    expect($admin->tenant_id)->toBeNull();

    test()->actingAs($admin)
        ->get(route('admin.tenant.index'))
        ->assertOk();
});

it('refuses a tenant-scoped user (tenant_id set), even with an unrestricted role', function () {
    $admin = getDefaultAdmin();

    // Confirms the gate reads the raw tenant_id column directly — it must
    // refuse this user even though their role is otherwise unrestricted
    // ("all permissions"), since permission_type=='all' means "unrestricted
    // within my own tenant," not "not tied to any tenant."
    expect($admin->role->permission_type)->toBe('all');

    $tenant = app(TenantRepository::class)->create([
        'name' => 'Scoped Co',
        'code' => 'scoped-co-'.uniqid(),
        'is_active' => true,
    ]);

    $admin->forceFill(['tenant_id' => $tenant->id])->save();

    test()->actingAs($admin->fresh())
        ->get(route('admin.tenant.index'))
        ->assertForbidden();
});

it('lets a super-admin create, edit and delete a tenant end to end', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->post(route('admin.tenant.store'), [
            'name' => 'Globex Test',
            'code' => 'globex-test-'.uniqid(),
            'is_active' => '1',
            'admin_name' => 'Globex Admin',
            'admin_email' => uniqid().'@globex-test.example',
            'admin_password' => 'password123',
            'admin_confirm_password' => 'password123',
        ])
        ->assertRedirect(route('admin.tenant.index'));

    $tenant = app(TenantRepository::class)->findOneWhere(['name' => 'Globex Test']);

    expect($tenant)->not->toBeNull()
        ->and($tenant->is_active)->toBeTrue();

    // Creating a tenant through this endpoint provisions everything it
    // needs to actually be usable, not just the bare tenant row — a
    // pipeline (with a stage) and an administrator role, both scoped to
    // the new tenant, plus the first user itself.
    $pipeline = Pipeline::where('tenant_id', $tenant->id)->first();

    expect($pipeline)->not->toBeNull()
        ->and($pipeline->stages)->not->toBeEmpty();

    $role = Role::where('tenant_id', $tenant->id)->first();

    expect($role)->not->toBeNull()
        ->and($role->permission_type)->toBe('all');

    $tenantAdmin = User::where('tenant_id', $tenant->id)->first();

    expect($tenantAdmin)->not->toBeNull()
        ->and($tenantAdmin->email)->toContain('@globex-test.example')
        ->and($tenantAdmin->role_id)->toBe($role->id);

    test()->actingAs($admin)
        ->put(route('admin.tenant.update', $tenant->id), [
            'name' => 'Globex Test Renamed',
            'code' => $tenant->code,
        ])
        ->assertRedirect(route('admin.tenant.index'));

    expect($tenant->fresh()->name)->toBe('Globex Test Renamed')
        // is_active wasn't sent this time — the checkbox convention means
        // "absent" is a real, deliberate uncheck, not an oversight.
        ->and($tenant->fresh()->is_active)->toBeFalse();

    test()->actingAs($admin)
        ->delete(route('admin.tenant.destroy', $tenant->id))
        ->assertOk();

    expect(app(TenantRepository::class)->find($tenant->id))->toBeNull();
});

it('does not let a tenant code collide with an existing one', function () {
    $admin = getDefaultAdmin();

    app(TenantRepository::class)->create([
        'name' => 'First Co',
        'code' => 'dupe-code',
        'is_active' => true,
    ]);

    test()->actingAs($admin)
        ->post(route('admin.tenant.store'), [
            'name' => 'Second Co',
            'code' => 'dupe-code',
            'is_active' => '1',
            'admin_name' => 'Second Co Admin',
            'admin_email' => uniqid().'@second-co.example',
            'admin_password' => 'password123',
            'admin_confirm_password' => 'password123',
        ])
        ->assertSessionHasErrors('code');
});

it('adds another user to an existing tenant, reusing its provisioned role', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)->post(route('admin.tenant.store'), [
        'name' => 'Initech Test',
        'code' => 'initech-test-'.uniqid(),
        'is_active' => '1',
        'admin_name' => 'Initech Admin',
        'admin_email' => uniqid().'@initech-test.example',
        'admin_password' => 'password123',
        'admin_confirm_password' => 'password123',
    ]);

    $tenant = app(TenantRepository::class)->findOneWhere(['name' => 'Initech Test']);
    $role = Role::where('tenant_id', $tenant->id)->first();

    test()->actingAs($admin)
        ->post(route('admin.tenant.users.store', $tenant->id), [
            'new_user_name' => 'Second Initech User',
            'new_user_email' => uniqid().'@initech-test.example',
            'new_user_password' => 'password123',
            'new_user_confirm_password' => 'password123',
        ])
        ->assertRedirect(route('admin.tenant.edit', $tenant->id));

    $newUser = User::where('tenant_id', $tenant->id)->where('name', 'Second Initech User')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->role_id)->toBe($role->id);
});
