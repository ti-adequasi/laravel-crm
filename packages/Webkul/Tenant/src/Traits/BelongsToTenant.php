<?php

namespace Webkul\Tenant\Traits;

use Webkul\Tenant\Scopes\TenantScope;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Applied directly to every tenant-owned model (not via Contract/Proxy
 * override) — Proxy resolution is bypassed for this app's single most
 * commonly resolved model (User, via config/auth.php's hardcoded
 * provider class) and for six others resolved via AdminServiceProvider's
 * Relation::morphMap(), so a mixed strategy would leave exactly those
 * models silently unscoped. Direct and uniform is safer here than clever.
 */
trait BelongsToTenant
{
    /**
     * Boot the trait: auto-stamp tenant_id on create, and filter every
     * query to the current tenant.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = CurrentTenant::id();

                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * The tenant_id column, qualified with this model's table — avoids
     * ambiguity once a query joins in another tenant-owned table.
     */
    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable().'.tenant_id';
    }
}
