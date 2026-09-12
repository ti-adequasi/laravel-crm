<?php

use Illuminate\Support\Facades\Route;
use Webkul\Pbx\Http\Controllers\PbxCallController;
use Webkul\Pbx\Http\Controllers\PbxCallHistoryController;
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

        // Bound by call_uuid, not the row's numeric id — that's the only
        // identifier the calling button's own JS ever sees (see
        // PbxCallController::originate()'s response).
        Route::controller(PbxCallController::class)->prefix('pbx/calls')->group(function () {
            Route::post('', 'originate')->name('admin.pbx.calls.originate');
            Route::get('{callUuid}/status', 'status')->name('admin.pbx.calls.status');
            Route::delete('{callUuid}', 'hangup')->name('admin.pbx.calls.hangup');
        });

        // Own prefix (not nested under pbx/calls) — a live PBX CDR proxy,
        // unrelated to the pbx_calls table the routes above track.
        Route::controller(PbxCallHistoryController::class)->prefix('pbx/history')->group(function () {
            Route::get('', 'index')->name('admin.pbx.history.index');
            Route::get('{xmlCdrUuid}/recording-url', 'recordingUrl')->name('admin.pbx.history.recording-url');
            Route::get('{xmlCdrUuid}/intel', 'intel')->name('admin.pbx.history.intel');
            Route::post('{xmlCdrUuid}/analyze', 'analyze')->name('admin.pbx.history.analyze');
        });
    });
