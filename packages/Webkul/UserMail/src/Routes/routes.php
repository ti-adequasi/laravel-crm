<?php

use Illuminate\Support\Facades\Route;
use Webkul\UserMail\Http\Controllers\UserMailAccountController;

// Registered independently of AdminServiceProvider's own route group —
// every query here is already scoped to the authenticated user's own
// user_id regardless, so this was never exploitable in practice, but
// 'tenant' is added anyway for consistency with every other package's
// routes and to close the gap before some future change here starts
// relying on tenant scoping without it. See LeadGreen's routes.php for
// why 'tenant' must precede 'user' (really Bouncer, not Laravel's auth
// middleware).
Route::middleware(['web', 'admin_locale', 'tenant', 'user'])
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
