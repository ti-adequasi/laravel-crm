<?php

namespace Webkul\Tenant\Support;

use Webkul\Tenant\Repositories\TenantRepository;

class CurrentTenant
{
    /**
     * Set only for the lifetime of a runAsNullTenant() callback — see
     * shouldScopeToNullTenant() and TenantScope::apply() for why this
     * needs to be a separate flag rather than reusing id() === null.
     */
    private static bool $scopingToNullTenant = false;

    /**
     * The currently-bound tenant id for this request/process, or null for
     * a super-admin / a console context where no tenant was ever resolved
     * (ResolveTenant middleware only runs on admin HTTP requests — a
     * scheduled command or `php artisan tinker` sees everything unscoped
     * unless it explicitly opts in via runAs()).
     */
    public static function id(): ?int
    {
        return app()->bound('currentTenantId') ? app('currentTenantId') : null;
    }

    /**
     * Run a callback with a specific tenant bound as "current" — for
     * console/scheduled-command contexts that need to process one tenant
     * at a time (see Phase 2.5). Restores whatever was bound before,
     * rather than assuming it should end up unbound, so nested calls
     * compose correctly.
     */
    public static function runAs(?int $tenantId, callable $callback): mixed
    {
        $wasBound = app()->bound('currentTenantId');
        $previous = $wasBound ? app('currentTenantId') : null;

        app()->instance('currentTenantId', $tenantId);

        try {
            return $callback();
        } finally {
            if ($wasBound) {
                app()->instance('currentTenantId', $previous);
            } else {
                app()->forgetInstance('currentTenantId');
            }
        }
    }

    /**
     * Whether TenantScope should filter to tenant_id IS NULL specifically,
     * rather than apply no filter at all. Deliberately separate from
     * id() === null: that state means "no tenant bound", under which a
     * super-admin's ordinary request must see every tenant's rows
     * unfiltered — this flag instead means "processing the null-tenant
     * bucket on purpose", which needs the opposite. Only ever true inside
     * runAsNullTenant()'s own callback.
     */
    public static function shouldScopeToNullTenant(): bool
    {
        return static::$scopingToNullTenant;
    }

    /**
     * Run a callback scoped specifically to tenant_id IS NULL rows — the
     * super-admin's own data, or anything predating multi-tenancy. Not
     * the same as runAs(null, ...) alone: that leaves TenantScope
     * unfiltered (correct for an interactive super-admin request, who
     * must see every tenant's data), whereas this additionally flips
     * shouldScopeToNullTenant() so the scope filters to WHERE tenant_id
     * IS NULL instead of not filtering at all — the distinction
     * eachActiveTenant()'s own final pass depends on.
     */
    public static function runAsNullTenant(callable $callback): mixed
    {
        $wasScoping = static::$scopingToNullTenant;

        static::$scopingToNullTenant = true;

        try {
            return static::runAs(null, $callback);
        } finally {
            static::$scopingToNullTenant = $wasScoping;
        }
    }

    /**
     * Prefix a storage path with the current tenant's own subtree
     * (tenants/{id}/...), or leave it unprefixed for a super-admin (no
     * tenant bound) — matching every file's existing, un-prefixed shape
     * exactly, so nothing already on disk needs to move and no read-side
     * fallback is needed: each row's own stored path is self-describing
     * regardless of which shape it has (see Phase 2.4).
     *
     * This is collision-avoidance and obscurity, not real access control
     * — most disks this touches are Laravel's `public` disk, symlinked
     * straight into the webroot, so an unguessable path is still directly
     * fetchable by anyone who guesses or enumerates it. Real protection
     * for a given upload also needs its model to carry BelongsToTenant
     * (so a scoped find() 404s a cross-tenant id before a path is even
     * read back) or, for the one write site whose model deliberately
     * doesn't (core_config, see Phase 2.3), an explicit tenant_id check
     * in the controller that serves it back.
     */
    public static function scopedStoragePath(string $path): string
    {
        $tenantId = static::id();

        return $tenantId === null ? $path : "tenants/{$tenantId}/{$path}";
    }

    /**
     * Run a callback once per active tenant, plus once more scoped to
     * tenant_id IS NULL (data predating multi-tenancy, or anything a
     * super-admin owns directly — see runAsNullTenant()). The shape a
     * scheduled command needs (Phase 2.5) so every tenant gets its own
     * fair slice of a per-run budget instead of one unscoped pass where a
     * single large tenant can starve smaller ones, and so a query that
     * joins across tenant-owned tables (campaigns to their recipients,
     * say) can't cross tenant boundaries by never having a tenant bound
     * at all. $callback receives the tenant id being processed (null on
     * the last pass) purely for logging — CurrentTenant::id() already
     * reflects it for anything the callback itself queries.
     */
    public static function eachActiveTenant(callable $callback): void
    {
        $tenantIds = app(TenantRepository::class)
            ->findWhere(['is_active' => true])
            ->pluck('id');

        foreach ($tenantIds as $tenantId) {
            static::runAs($tenantId, fn () => $callback($tenantId));
        }

        static::runAsNullTenant(fn () => $callback(null));
    }
}
