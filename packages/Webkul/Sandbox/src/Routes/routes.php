<?php

use Illuminate\Support\Facades\Route;
use Webkul\Sandbox\Http\Controllers\NoteController;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(NoteController::class)->prefix('sandbox/notes')->group(function () {
            Route::get('', 'index')->name('admin.sandbox.notes.index');

            Route::post('', 'store')->name('admin.sandbox.notes.store');

            Route::delete('{id}', 'destroy')->name('admin.sandbox.notes.destroy');
        });
    });
