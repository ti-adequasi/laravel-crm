<?php

namespace Webkul\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Like TenantScope, except a bound tenant sees its OWN rows *plus* every
 * shared/global one (tenant_id IS NULL) — for tables that hold system-wide
 * definitions every tenant is meant to see by default (attribute/field
 * definitions: Person's "Contact Numbers", Lead's "Title", and so on),
 * with per-tenant customization as the exception rather than the norm.
 *
 * TenantScope's own no-fallback behavior is correct — even necessary —
 * for a table like pbx_settings, where a tenant with no row of its own
 * must never see another tenant's (that's a real leak, not a convenience
 * gap). This scope exists for the opposite case, where the "global" rows
 * are shared system data everyone is supposed to see, not another
 * tenant's private data leaking through: confirmed a real, previously-
 * unnoticed gap for `attributes` specifically — every core field
 * definition (Person's contact_numbers, emails, etc.) is seeded with
 * tenant_id NULL, so a genuinely tenant-bound request (any real,
 * non-super-admin user) saw zero attributes at all for every entity type,
 * silently skipping field-level validation everywhere rather than
 * rejecting anything outright — not caught earlier because every test
 * exercising these repositories so far called them directly
 * (LeadRepository::create(), etc.), never through the actual HTTP
 * controllers/FormRequests that query attributes by name.
 */
class TenantOrGlobalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId !== null) {
            $column = $model->getQualifiedTenantColumn();

            $builder->where(function ($query) use ($column, $tenantId) {
                $query->where($column, $tenantId)->orWhereNull($column);
            });

            return;
        }

        if (CurrentTenant::shouldScopeToNullTenant()) {
            $builder->whereNull($model->getQualifiedTenantColumn());
        }
    }
}
