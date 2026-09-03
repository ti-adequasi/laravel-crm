<?php

namespace Webkul\LeadGreen\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Webkul\LeadGreen\Console\Commands\EnrichPendingLeadGreenProspects;

class LeadGreenServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'leadgreen');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'leadgreen');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EnrichPendingLeadGreenProspects::class,
            ]);

            $this->app->booted(function () {
                $this->app->make(Schedule::class)
                    ->command(EnrichPendingLeadGreenProspects::class)
                    ->everyMinute()
                    ->withoutOverlapping();
            });
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
