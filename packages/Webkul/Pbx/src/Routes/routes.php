<?php

use Illuminate\Support\Facades\Route;
use Webkul\Pbx\Http\Controllers\PbxSettingController;

// 'tenant' before 'user' (really Bouncer, not Laravel's auth middleware) —
// see crm-package-development/SKILL.md's own section on this. Getting this
// order wrong is exactly the bug Phase 2.6 found and fixed in four other
// packages that register their own routes this same way.
Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(PbxSettingController::class)->prefix('pbx')->group(function () {
            Route::get('', 'edit')->name('admin.pbx.edit');
            Route::put('', 'update')->name('admin.pbx.update');
            Route::post('test', 'test')->name('admin.pbx.test');
        });
    });
