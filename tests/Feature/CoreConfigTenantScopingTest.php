<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Tenant\Repositories\TenantRepository;

uses(DatabaseTransactions::class);

/**
 * Mirrors the real save path (ConfigurationController::store() ->
 * CoreConfigRepository::create($request->all())) with the same nested
 * shape a real form submits, rather than writing to core_config directly
 * — the nesting is exactly what recursiveArray() has to flatten back down
 * to a single 'general.magic_ai.settings.api_key' code.
 */
function saveMagicAiApiKeyAs(?int $tenantId, string $apiKey): void
{
    app()->instance('currentTenantId', $tenantId);

    app(CoreConfigRepository::class)->create([
        'general' => [
            'magic_ai' => [
                'settings' => [
                    'api_key' => $apiKey,
                ],
            ],
        ],
    ]);
}

function magicAiApiKeyAs(?int $tenantId): mixed
{
    app()->instance('currentTenantId', $tenantId);

    return system_config()->getConfigData('general.magic_ai.settings.api_key');
}

it('keeps two tenants\' saves of the same config key from mixing', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'Config Tenant A', 'code' => 'config-tenant-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Config Tenant B', 'code' => 'config-tenant-b-'.uniqid(), 'is_active' => true]);

    saveMagicAiApiKeyAs($tenantA->id, 'sk-tenant-a-key');
    saveMagicAiApiKeyAs($tenantB->id, 'sk-tenant-b-key');

    expect(magicAiApiKeyAs($tenantA->id))->toBe('sk-tenant-a-key')
        ->and(magicAiApiKeyAs($tenantB->id))->toBe('sk-tenant-b-key');
});

it('lets a tenant save its own key without ever touching the global row', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Config Tenant C', 'code' => 'config-tenant-c-'.uniqid(), 'is_active' => true]);

    saveMagicAiApiKeyAs(null, 'sk-global-key');
    saveMagicAiApiKeyAs($tenant->id, 'sk-tenant-c-key');

    expect(magicAiApiKeyAs($tenant->id))->toBe('sk-tenant-c-key')
        // The super-admin's own (global) row must read back unchanged —
        // a tenant's save must never have overwritten it.
        ->and(magicAiApiKeyAs(null))->toBe('sk-global-key');
});

it('falls back to the global row for a tenant that never set its own value', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Config Tenant D', 'code' => 'config-tenant-d-'.uniqid(), 'is_active' => true]);

    saveMagicAiApiKeyAs(null, 'sk-global-fallback-key');

    expect(magicAiApiKeyAs($tenant->id))->toBe('sk-global-fallback-key');
});

it('falls back to the field\'s own default when neither a tenant row nor a global row exists', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Config Tenant E', 'code' => 'config-tenant-e-'.uniqid(), 'is_active' => true]);

    // This install has a real, already-configured global value for this
    // field from ordinary use elsewhere in this test database — the
    // assertion below is about the *absence* of any row, so that ambient
    // value has to be cleared first rather than assumed away.
    CoreConfig::where('code', 'general.magic_ai.settings.provider')->whereNull('tenant_id')->delete();

    app()->instance('currentTenantId', $tenant->id);

    // core_config.php declares 'openrouter' as this field's default — the
    // same fallback getConfigData() has always had, now reached only
    // after both scoping levels come up empty.
    expect(system_config()->getConfigData('general.magic_ai.settings.provider'))->toBe('openrouter');
});

it('updates an existing tenant row in place instead of creating a duplicate', function () {
    $tenant = app(TenantRepository::class)->create(['name' => 'Config Tenant F', 'code' => 'config-tenant-f-'.uniqid(), 'is_active' => true]);

    saveMagicAiApiKeyAs($tenant->id, 'sk-first-value');
    saveMagicAiApiKeyAs($tenant->id, 'sk-second-value');

    expect(magicAiApiKeyAs($tenant->id))->toBe('sk-second-value')
        ->and(
            CoreConfig::where('code', 'general.magic_ai.settings.api_key')
                ->where('tenant_id', $tenant->id)
                ->count()
        )->toBe(1);
});
