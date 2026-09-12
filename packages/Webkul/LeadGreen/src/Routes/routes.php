<?php

use Illuminate\Support\Facades\Route;
use Webkul\LeadGreen\Http\Controllers\LeadGreenController;

// This package registers its own routes independently of
// Webkul\Admin\Providers\AdminServiceProvider's Routes/Admin/web.php
// group — 'tenant' (ResolveTenant) is never applied unless listed here
// too. Missing it left LeadGreen (a BelongsToTenant model since Phase
// 2.2) with no tenant ever bound and therefore no scoping at all: any
// tenant could list, view, convert, enrich, discard or export any other
// tenant's prospects. 'tenant' before 'user' matches AdminServiceProvider's
// own ordering (see crm-package-development/SKILL.md) — 'user' is really
// Bouncer, and its own tenant-scoped role lookup must see this request's
// tenant already bound, not one left over from earlier.
Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
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
