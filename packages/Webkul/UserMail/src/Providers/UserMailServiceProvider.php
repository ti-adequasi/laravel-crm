<?php

namespace Webkul\UserMail\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;

class UserMailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'user_mail');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'user_mail');

        $this->app->register(ModuleServiceProvider::class);

        // Inject the "My Email Account" accordion into the existing My
        // Account screen — no core file touched. See view_render_event()
        // in Webkul\Core\Http\helpers.
        Event::listen('admin.user.account.right.after', function (ViewRenderEventManager $manager) {
            $manager->addTemplate('user_mail::partials.account');
        });
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }
}
