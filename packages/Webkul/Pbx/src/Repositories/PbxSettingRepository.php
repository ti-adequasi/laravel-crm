<?php

namespace Webkul\Pbx\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Pbx\Contracts\PbxSetting;
use Webkul\Tenant\Support\CurrentTenant;

class PbxSettingRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return PbxSetting::class;
    }

    /**
     * The current tenant's own row, or a fresh unsaved instance if none
     * exists yet — the model's own BelongsToTenant scope already confines
     * this to the current tenant with no fallback, so "no row" reliably
     * means "not configured for this tenant", never another tenant's.
     */
    public function findForCurrentTenant(): PbxSetting
    {
        return $this->model->first() ?? $this->model->newInstance([
            'tenant_id' => CurrentTenant::id(),
            'enabled' => false,
        ]);
    }

    /**
     * Create or update the current tenant's own row — never any other
     * tenant's, regardless of what's passed in $data (tenant_id is
     * intentionally not accepted from the caller here).
     */
    public function saveForCurrentTenant(array $data): PbxSetting
    {
        $setting = $this->model->first();

        $attributes = [
            'enabled' => $data['enabled'] ?? false,
        ];

        // Only overwrite a previously-saved key if a new one was actually
        // submitted — the edit form never re-displays the real key (it's
        // encrypted and hidden), so a blank submission must leave it alone
        // rather than wiping it out.
        if (! empty($data['api_key'])) {
            $attributes['api_key'] = $data['api_key'];
        }

        if ($setting) {
            $setting->update($attributes);

            return $setting->fresh();
        }

        return $this->model->create([
            ...$attributes,
            'tenant_id' => CurrentTenant::id(),
        ]);
    }
}
