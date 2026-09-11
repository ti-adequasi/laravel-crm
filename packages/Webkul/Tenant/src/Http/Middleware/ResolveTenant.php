<?php

namespace Webkul\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    /**
     * Bind the logged-in user's tenant_id for the rest of the request —
     * same shape as Webkul\Admin\Http\Middleware\Locale, which does the
     * equivalent for the active locale. Runs BEFORE 'user' (the alias for
     * Bouncer, the auth+ACL check) on purpose: Bouncer loads $user->role,
     * itself tenant-scoped, and must see this request's own tenant already
     * bound rather than whatever a prior request in the same process last
     * left behind. Resolving the user here (rather than relying on 'user'
     * having done it first) is what makes that ordering safe.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->guard('user')->user();

        app()->instance('currentTenantId', $user?->tenant_id);

        return $next($request);
    }
}
