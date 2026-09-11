<?php

use Illuminate\Support\Facades\Route;
use Webkul\Tenant\Http\Controllers\TenantController;

// Deliberately its own top-level prefix (config('app.super_admin_path'), not
// admin_path) — a dedicated area for cross-tenant management, so it never
// mixes into a tenant-scoped admin's own sidebar/URLs. Route *names* keep
// their pre-existing 'admin.tenant.*' shape (menu.php, breadcrumbs.php and
// the controller's own redirects already reference them) — only the URL
// path changes.
Route::middleware(['web', 'admin_locale', 'user', 'super_admin'])
    ->prefix(config('app.super_admin_path'))
    ->group(function () {
        Route::controller(TenantController::class)->prefix('tenants')->group(function () {
            Route::get('', 'index')->name('admin.tenant.index');
            Route::get('create', 'create')->name('admin.tenant.create');
            Route::post('create', 'store')->name('admin.tenant.store');
            Route::get('edit/{id}', 'edit')->name('admin.tenant.edit');
            Route::put('edit/{id}', 'update')->name('admin.tenant.update');
            Route::delete('{id}', 'destroy')->name('admin.tenant.destroy');
            Route::post('edit/{id}/users', 'storeUser')->name('admin.tenant.users.store');
        });
    });
