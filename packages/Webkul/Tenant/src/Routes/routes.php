<?php

use Illuminate\Support\Facades\Route;
use Webkul\Tenant\Http\Controllers\TenantController;

Route::middleware(['web', 'admin_locale', 'user', 'super_admin'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(TenantController::class)->prefix('tenants')->group(function () {
            Route::get('', 'index')->name('admin.tenant.index');
            Route::get('create', 'create')->name('admin.tenant.create');
            Route::post('create', 'store')->name('admin.tenant.store');
            Route::get('edit/{id}', 'edit')->name('admin.tenant.edit');
            Route::put('edit/{id}', 'update')->name('admin.tenant.update');
            Route::delete('{id}', 'destroy')->name('admin.tenant.destroy');
        });
    });
