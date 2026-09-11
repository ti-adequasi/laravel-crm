<?php

use Illuminate\Support\Facades\Route;
use Webkul\UserMail\Http\Controllers\UserMailAccountController;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(UserMailAccountController::class)
            ->prefix('account/email-account')
            ->group(function () {
                Route::put('', 'update')->name('admin.user_mail.account.update');
                Route::delete('', 'destroy')->name('admin.user_mail.account.delete');
                Route::post('test', 'test')->name('admin.user_mail.account.test');
            });
    });
