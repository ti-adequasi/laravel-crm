<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Tenant\Repositories\TenantRepository;
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
        ])
        ->assertRedirect(route('admin.tenant.index'));

    $tenant = app(TenantRepository::class)->findOneWhere(['name' => 'Globex Test']);

    expect($tenant)->not->toBeNull()
        ->and($tenant->is_active)->toBeTrue();

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
        ])
        ->assertSessionHasErrors('code');
});
