<?php

use Illuminate\Support\Facades\Route;
use Webkul\WebForm\Http\Controllers\WebFormController;

// formJS/preview/formStore are deliberately public and unauthenticated —
// a third-party site embeds these with no login of its own, so there is
// no admin user to resolve a tenant from via the ordinary 'tenant'
// middleware (ResolveTenant reads auth()->guard('user')->user(), which
// is always null here). formStore instead derives the tenant from the
// WebForm row itself once it's loaded (CurrentTenant::runAs($webForm->
// tenant_id, ...)), the only place that tenant is knowable at all for a
// public request — see WebFormController::formStore(). Without it, every
// Lead/Person a public visitor submitted was created with tenant_id
// NULL instead of the form's own tenant, invisible to that tenant's own
// admin view.
Route::controller(WebFormController::class)->middleware(['web', 'admin_locale'])->prefix('web-forms')->group(function () {
    Route::get('forms/{id}/form.js', 'formJS')->name('admin.settings.web_forms.form_js');

    Route::get('forms/{id}/form.html', 'preview')->name('admin.settings.web_forms.preview');

    Route::post('forms/{id}', 'formStore')->name('admin.settings.web_forms.form_store');

    // This one IS admin-authenticated ('user' — really Bouncer, see
    // crm-package-development/SKILL.md), so it gets 'tenant' like any
    // other authenticated route; 'tenant' before 'user' for the same
    // reason as every other package's routes.php.
    Route::group(['middleware' => ['tenant', 'user']], function () {
        Route::get('form/{id}/form.html', 'view')->name('admin.settings.web_forms.view');
    });
});
