<?php

namespace Webkul\LeadEnrichment\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;

class LeadEnrichmentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'lead_enrichment');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'lead_enrichment');

        // Inject the "Enrich" button into the lead detail page's action bar —
        // no core file touched. See view_render_event() in Webkul\Core\Http\helpers.
        Event::listen('admin.leads.view.actions.after', function (ViewRenderEventManager $manager) {
            $manager->addTemplate('lead_enrichment::partials.enrich-button');
        });
    }
}
