<?php

use Illuminate\Support\Facades\Route;
use Webkul\LeadEnrichment\Http\Controllers\EnrichmentController;

// Registered independently of AdminServiceProvider's own route group —
// without 'tenant' bound, Lead::find($id) below was unscoped, letting
// any tenant enrich (and read back) any other tenant's lead by id. See
// LeadGreen's own routes.php for the same fix and why 'tenant' must
// precede 'user' (really Bouncer, not Laravel's auth middleware).
Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::post('leads/{id}/enrich', [EnrichmentController::class, 'enrich'])
            ->name('admin.leads.enrich');
    });
