<?php

namespace Webkul\Tenant\Support;

class CurrentTenant
{
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
}
