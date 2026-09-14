<?php

use Illuminate\Support\Facades\Route;
use Webkul\LeadPeering\Http\Controllers\LeadPeeringController;

// This package registers its own routes independently of
// Webkul\Admin\Providers\AdminServiceProvider's Routes/Admin/web.php
// group — 'tenant' (ResolveTenant) is never applied unless listed here
// too. A sibling module (LeadGreen) once shipped without it and, being a
// BelongsToTenant model, ended up with no tenant ever bound: any tenant
// could list, view, convert, enrich, discard or export any other tenant's
// prospects (see crm-package-development/SKILL.md). 'tenant' before 'user'
// matches AdminServiceProvider's own ordering — 'user' is really Bouncer,
// and its own tenant-scoped role lookup must see this request's tenant
// already bound, not one left over from earlier.
Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(LeadPeeringController::class)->prefix('leadpeering')->group(function () {
            Route::get('', 'index')->name('admin.leadpeering.index');

            Route::get('search', 'searchForm')->name('admin.leadpeering.search.form');

            Route::post('search', 'search')->name('admin.leadpeering.search');

            Route::post('import', 'import')->name('admin.leadpeering.import');

            Route::get('view/{id}', 'view')->name('admin.leadpeering.view');

            // POST, unlike LeadGreen's equivalent (a GET that mutates state)
            // — see LeadPeeringController::convert()'s docblock.
            Route::post('convert/{id}', 'convert')->name('admin.leadpeering.convert');

            Route::post('enrich/{id}', 'enrich')->name('admin.leadpeering.enrich');

            Route::get('enrichment-status', 'enrichmentStatus')->name('admin.leadpeering.enrichment-status');

            Route::post('discard/{id}', 'discard')->name('admin.leadpeering.discard');

            Route::get('export', 'export')->name('admin.leadpeering.export');
        });
    });
