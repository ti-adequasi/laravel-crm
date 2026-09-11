<?php

namespace Webkul\Admin\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Bouncer;
use Webkul\Admin\Exceptions\Handler;
use Webkul\Admin\Http\Middleware\Bouncer as BouncerMiddleware;
use Webkul\Admin\Http\Middleware\Locale;
use Webkul\Admin\Http\Middleware\SanitizeUrl;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Webkul\Warehouse\Models\Warehouse;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('user', BouncerMiddleware::class);

        $router->aliasMiddleware('admin_locale', Locale::class);

        $router->aliasMiddleware('sanitize_url', SanitizeUrl::class);

        include __DIR__.'/../Http/helpers.php';

        // 'tenant' (Webkul\Tenant\Http\Middleware\ResolveTenant, aliased from
        // that package's own provider) binds the logged-in user's tenant_id
        // for the rest of the request — every tenant-scoped model's global
        // scope reads it from there. See crm-package-development/SKILL.md's
        // multi-tenancy notes for why this is a direct edit rather than a
        // hook: there's no way to inject a 4th entry into this array from
        // another package's provider without one.
        //
        // 'tenant' must run BEFORE 'user' (confusingly, the alias for
        // Webkul\Admin\Http\Middleware\Bouncer — the auth+ACL check, not
        // Laravel's own auth middleware). Bouncer loads $user->role, and
        // Role is itself tenant-scoped; if it ran first, that lookup would
        // use whatever tenant happened to be bound from a PRIOR request in
        // the same process (a queue worker, an artisan command chaining
        // calls, or a test) instead of the one this request actually
        // belongs to — discovered via a real 401 in
        // tests/Feature/TenantScopingTest.php that a fresh per-request boot
        // never hits, but a long-lived process genuinely can.
        Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/Admin/web.php');

        Route::middleware(['web', 'admin_locale'])
            ->group(__DIR__.'/../Routes/Front/web.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'admin');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admin');

        Blade::anonymousComponentPath(__DIR__.'/../Resources/views/components', 'admin');

        $this->app->bind(ExceptionHandler::class, Handler::class);

        Relation::morphMap([
            'leads' => Lead::class,
            'organizations' => Organization::class,
            'persons' => Person::class,
            'products' => Product::class,
            'quotes' => Quote::class,
            'warehouses' => Warehouse::class,
        ]);

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFacades();

        $this->registerConfig();
    }

    /**
     * Register Bouncer as a singleton.
     */
    protected function registerFacades(): void
    {
        $loader = AliasLoader::getInstance();

        $loader->alias('Bouncer', \Webkul\Admin\Facades\Bouncer::class);

        $this->app->singleton('bouncer', function () {
            return new Bouncer;
        });
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/attribute_lookups.php', 'attribute_lookups');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/attribute_entity_types.php', 'attribute_entity_types');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/support.php', 'support');
    }
}
