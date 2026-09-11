<?php

namespace Webkul\Tenant\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Tenant\Http\Middleware\EnsureSuperAdmin;

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
