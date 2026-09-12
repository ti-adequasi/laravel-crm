<?php

namespace Webkul\Pbx\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Pbx\Console\Commands\ReconcilePbxCalls;

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

        $this->registerCommands();

        // Injects the "Ramal" field into the existing Users create/edit
        // modal — no edit to that core Blade file needed, since every
        // field there is already individually hook-wrapped.
        Event::listen('admin.settings.users.index.form.status.after', function (ViewRenderEventManager $manager) {
            $manager->addTemplate('pbx::partials.user-extension-field');
        });

        // Injects a "Ligar" click-to-call button next to each of a Lead's
        // Person's phone numbers — this one new per-row event (added to
        // person.blade.php alongside its pre-existing .before/.after pair,
        // which only wrap the whole list) is genuinely new, not something
        // this package is merely the first to use.
        Event::listen('admin.leads.view.person.contact_numbers.row', function (ViewRenderEventManager $manager) {
            $manager->addTemplate('pbx::partials.call-button');
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

    /**
     * Register the console commands of this package — matching Email's and
     * LeadGreen's own registerCommands() pattern. Artisan doesn't auto-
     * discover a package's Console/Commands directory the way it does the
     * main app's, so without this, `pbx:reconcile-calls` would silently
     * not exist as a command at all despite the class being present.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcilePbxCalls::class,
            ]);
        }
    }
}
