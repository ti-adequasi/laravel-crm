<?php

namespace Webkul\LeadPeering\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\LeadPeering\Console\Commands\EnrichPendingLeadPeeringProspects;

class LeadPeeringServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'leadpeering');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'leadpeering');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EnrichPendingLeadPeeringProspects::class,
            ]);

            // Scheduling itself deliberately does NOT happen here — unlike
            // LeadGreen's provider (which schedules itself via a
            // $this->app->booted() hook), this app's real, documented
            // convention is routes/console.php as the single place a
            // command is actually scheduled (see crm-package-development/
            // SKILL.md, "Package-Owned Scheduled Commands"). See
            // routes/console.php for leadpeering:enrich-pending's
            // Schedule::command() entry.
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');
    }
}
