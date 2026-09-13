<?php

namespace Webkul\Tenant\Traits;

use Webkul\Tenant\Scopes\TenantOrGlobalScope;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Same auto-stamp-on-create as BelongsToTenant (a tenant creating its own
 * row — e.g. its own custom attribute — still gets its own tenant_id, so
 * it stays exclusive to them, not global), but reads through
 * TenantOrGlobalScope instead of TenantScope — see that class's own
 * docblock for which of the two a given table actually needs.
 */
trait BelongsToTenantOrGlobal
{
    protected static function bootBelongsToTenantOrGlobal(): void
    {
        static::addGlobalScope(new TenantOrGlobalScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = CurrentTenant::id();

                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable().'.tenant_id';
    }
}
