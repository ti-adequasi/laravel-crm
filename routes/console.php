<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sendgrid's inbound processor deliberately doesn't support bulk/polled
// processing (Sendgrid pushes mail via webhook instead) — it throws on
// every call. Skip the scheduled run entirely when that's the configured
// driver, rather than logging a guaranteed failure every 5 minutes.
Schedule::command('inbound-emails:process')
    ->everyFiveMinutes()
    ->when(fn () => config('mail-receiver.default') !== 'sendgrid');

// Catches click-to-call attempts whose browser tab closed before the call
// ended — the normal path (the browser's own live polling) already
// finalizes a call the moment it ends, so this only ever has work to do
// for the abandoned-tab case. See ReconcilePbxCalls's own docblock.
Schedule::command('pbx:reconcile-calls')->everyFiveMinutes();
