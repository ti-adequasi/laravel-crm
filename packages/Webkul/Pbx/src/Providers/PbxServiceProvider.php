<?php

namespace Webkul\Pbx\Providers;

use Illuminate\Support\ServiceProvider;

class PbxServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'pbx');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'pbx');

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/pbx.php', 'pbx');
    }
}
