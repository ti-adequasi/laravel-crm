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
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId !== null) {
            $builder->where($model->getQualifiedTenantColumn(), $tenantId);
        }
    }
}
