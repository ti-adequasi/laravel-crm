<?php

use Illuminate\Support\Facades\Route;
use Webkul\LeadEnrichment\Http\Controllers\EnrichmentController;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::post('leads/{id}/enrich', [EnrichmentController::class, 'enrich'])
            ->name('admin.leads.enrich');
    });
