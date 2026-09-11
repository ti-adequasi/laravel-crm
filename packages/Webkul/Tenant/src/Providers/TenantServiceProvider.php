<?php

namespace Webkul\Tenant\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Tenant\Http\Middleware\EnsureSuperAdmin;
use Webkul\Tenant\Http\Middleware\ResolveTenant;
use Webkul\Tenant\Listeners\ScopeDataGridToTenant;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'tenant');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'tenant');

        $this->app->register(ModuleServiceProvider::class);

        // Registered from this package's own provider — no edit to
        // AdminServiceProvider or any other core file needed for this alias.
        $router->aliasMiddleware('super_admin', EnsureSuperAdmin::class);

        $router->aliasMiddleware('tenant', ResolveTenant::class);

        // Every DataGrid's listing query bypasses Eloquent entirely (see
        // ScopeDataGridToTenant's own docblock) — this wildcard listener
        // is what actually scopes list views, not the Eloquent global scope.
        Event::listen('datagrid.*.query_builder.set.after', [ScopeDataGridToTenant::class, 'handle']);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }
}
