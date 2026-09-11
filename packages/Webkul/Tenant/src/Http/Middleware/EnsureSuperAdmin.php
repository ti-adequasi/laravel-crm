<?php

namespace Webkul\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    /**
     * A super-admin is a user with no tenant of their own (`tenant_id IS
     * NULL`) — deliberately a separate signal from ACL's "all permissions"
     * role type, which only means "unrestricted within my own tenant."
     * A tenant admin with an "all permissions" role must still be refused
     * here even though bouncer() would otherwise wave them through.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->guard('user')->user();

        abort_unless($user && is_null($user->tenant_id), 403);

        return $next($request);
    }
}
