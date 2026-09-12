<?php

namespace Webkul\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Webkul\Tenant\Support\CurrentTenant;

class TenantScope implements Scope
{
    /**
     * Filter every query to the current tenant — unless no tenant is
     * bound at all (a super-admin request, or a console context that
     * hasn't opted into a specific tenant), in which case no filter is
     * applied and the query sees every tenant's rows.
     *
     * The one exception is CurrentTenant::runAsNullTenant() — deliberately
     * a *different* state from plain "unbound", even though id() reads
     * null either way: a scheduled command giving the null-tenant bucket
     * (super-admin-owned / pre-multi-tenancy data) its own turn needs
     * WHERE tenant_id IS NULL specifically, not "no filter at all", which
     * would spill into every other tenant's rows too — exactly the leak
     * CurrentTenant::eachActiveTenant()'s own final pass exists to avoid.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId !== null) {
            $builder->where($model->getQualifiedTenantColumn(), $tenantId);

            return;
        }

        if (CurrentTenant::shouldScopeToNullTenant()) {
            $builder->whereNull($model->getQualifiedTenantColumn());
        }
    }
}
