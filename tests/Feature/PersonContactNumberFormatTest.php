<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\Tenant\Support\CurrentTenant;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * Confirmed live, against the real PBX, that its own outbound dial-plan
 * requires a literal leading "0" trunk-prefix digit + DDD + subscriber
 * number for outbound calls to complete at all (see
 * PhoneNumberNormalizer::normalizeForDialing()). Requested explicitly:
 * reject a Person's contact number at save time if it isn't already in
 * that exact shape, rather than only discovering a bad number later
 * trying to place a call. Enforced via `attributes.validation` on the
 * `contact_numbers` attribute (entity_type=persons) — a column that
 * already existed, seeded 'numeric', silently ignored until now.
 */
function makeContactFormatTestTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'Contact Format Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'Contact Format Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

/**
 * Created *as* the tenant (CurrentTenant::runAs), not created unscoped and
 * retroactively re-tenanted — the latter leaves this Person's own EAV
 * attribute_values rows (name, emails, ...) stamped tenant_id NULL while
 * the persons row itself says otherwise, and a later update through the
 * real HTTP endpoint then tries to *insert* a value for those fields
 * (its own tenant-scoped "does one already exist" lookup finds nothing)
 * and collides with the orphaned NULL-tenant row's own unique index.
 */
function makeContactFormatTestPerson(int $tenantId, string $name): Person
{
    return CurrentTenant::runAs($tenantId, fn () => app(PersonRepository::class)->create([
        'name' => $name,
        'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
        'entity_type' => 'persons',
    ]));
}

it('accepts a Person contact number already in the 0+DDD+number shape', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Contact Format Valid Tenant', 'code' => 'contact-format-valid-'.uniqid(), 'is_active' => true]);
    $user = makeContactFormatTestTenantUser($tenant->id);
    $person = makeContactFormatTestPerson($tenant->id, 'Format Test Person');

    test()->actingAs($user)
        ->put(route('admin.contacts.persons.update', $person->id), [
            'entity_type' => 'persons',
            'name' => $person->name,
            'emails' => $person->emails,
            'contact_numbers' => [['value' => '011987654321', 'label' => 'work']],
        ])
        ->assertRedirect();

    expect($person->fresh()->contact_numbers)->toBe([['value' => '011987654321', 'label' => 'work']]);
});

it('accepts a landline Person contact number in the 0+DDD+number shape', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Contact Format Landline Tenant', 'code' => 'contact-format-landline-'.uniqid(), 'is_active' => true]);
    $user = makeContactFormatTestTenantUser($tenant->id);
    $person = makeContactFormatTestPerson($tenant->id, 'Format Test Person Landline');

    test()->actingAs($user)
        ->put(route('admin.contacts.persons.update', $person->id), [
            'entity_type' => 'persons',
            'name' => $person->name,
            'emails' => $person->emails,
            'contact_numbers' => [['value' => '01132654321', 'label' => 'work']],
        ])
        ->assertRedirect();

    expect($person->fresh()->contact_numbers)->toBe([['value' => '01132654321', 'label' => 'work']]);
});

it('rejects a Person contact number missing the leading 0', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Contact Format No Zero Tenant', 'code' => 'contact-format-no-zero-'.uniqid(), 'is_active' => true]);
    $user = makeContactFormatTestTenantUser($tenant->id);
    $person = makeContactFormatTestPerson($tenant->id, 'Format Test Person No Zero');

    test()->actingAs($user)
        ->put(route('admin.contacts.persons.update', $person->id), [
            'entity_type' => 'persons',
            'name' => $person->name,
            'emails' => $person->emails,
            'contact_numbers' => [['value' => '11987654321', 'label' => 'work']],
        ])
        ->assertSessionHasErrors('contact_numbers.0.value');

    expect($person->fresh()->contact_numbers)->toBeEmpty();
});

it('rejects a Person contact number that is not a real Brazilian shape at all', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Contact Format Garbage Tenant', 'code' => 'contact-format-garbage-'.uniqid(), 'is_active' => true]);
    $user = makeContactFormatTestTenantUser($tenant->id);
    $person = makeContactFormatTestPerson($tenant->id, 'Format Test Person Garbage');

    test()->actingAs($user)
        ->put(route('admin.contacts.persons.update', $person->id), [
            'entity_type' => 'persons',
            'name' => $person->name,
            'emails' => $person->emails,
            'contact_numbers' => [['value' => '12345', 'label' => 'work']],
        ])
        ->assertSessionHasErrors('contact_numbers.0.value');
});

it('rejects an invalidly-formatted contact number on a new Person created inline through a Lead', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Contact Format Lead Tenant', 'code' => 'contact-format-lead-'.uniqid(), 'is_active' => true]);
    $user = makeContactFormatTestTenantUser($tenant->id);

    test()->actingAs($user)
        ->post(route('admin.leads.store'), [
            'entity_type' => 'leads',
            'title' => 'Contact Format Lead',
            'lead_value' => 100,
            'lead_pipeline_id' => 1,
            'lead_pipeline_stage_id' => 1,
            'status' => 1,
            'person' => [
                'name' => 'Inline Person',
                'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
                'contact_numbers' => [['value' => '11987654321', 'label' => 'work']],
                'entity_type' => 'persons',
            ],
        ])
        ->assertSessionHasErrors('person.contact_numbers.0.value');

    expect(app(LeadRepository::class)->findWhere(['title' => 'Contact Format Lead']))->toHaveCount(0);
});
