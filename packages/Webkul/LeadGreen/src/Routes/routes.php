<?php

use Illuminate\Support\Facades\Route;
use Webkul\LeadGreen\Http\Controllers\LeadGreenController;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(LeadGreenController::class)->prefix('leadgreen')->group(function () {
            Route::get('', 'index')->name('admin.leadgreen.index');

            Route::get('search', 'searchForm')->name('admin.leadgreen.search.form');

            Route::post('search', 'search')->name('admin.leadgreen.search');

            Route::post('import', 'import')->name('admin.leadgreen.import');

            Route::get('view/{id}', 'view')->name('admin.leadgreen.view');

            Route::get('convert/{id}', 'convert')->name('admin.leadgreen.convert');

            Route::post('enrich/{id}', 'enrich')->name('admin.leadgreen.enrich');

            Route::get('enrichment-status', 'enrichmentStatus')->name('admin.leadgreen.enrichment-status');

            Route::post('discard/{id}', 'discard')->name('admin.leadgreen.discard');

            Route::get('export', 'export')->name('admin.leadgreen.export');
        });
    });
