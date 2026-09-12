<?php

namespace Webkul\Pbx\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;

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

        // Injects the "Ramal" field into the existing Users create/edit
        // modal — no edit to that core Blade file needed, since every
        // field there is already individually hook-wrapped.
        Event::listen('admin.settings.users.index.form.status.after', function (ViewRenderEventManager $manager) {
            $manager->addTemplate('pbx::partials.user-extension-field');
        });
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
