<?php

namespace Webkul\Admin\Http\Controllers\Configuration;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\ConfigurationForm;
use Webkul\Core\Repositories\CoreConfigRepository as ConfigurationRepository;
use Webkul\Tenant\Support\CurrentTenant;

class ConfigurationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ConfigurationRepository $configurationRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        if (
            request()->route('slug')
            && request()->route('slug2')
        ) {
            return view('admin::configuration.edit');
        }

        return view('admin::configuration.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigurationForm $request): RedirectResponse
    {
        Event::dispatch('core.configuration.save.before');

        $this->configurationRepository->create($request->all());

        Event::dispatch('core.configuration.save.after');

        session()->flash('success', trans('admin::app.configuration.index.save-success'));

        return redirect()->back();
    }

    /**
     * download the file for the specified resource.
     *
     * core_config has no BelongsToTenant scope (Phase 2.3 — it needs a
     * global-row fallback a scope would just hide instead), so unlike
     * every other tenant-owned download in this codebase, a cross-tenant
     * id here isn't already refused by the model layer on its own; this
     * method is where that check has to live instead.
     *
     * @return Response
     */
    public function download()
    {
        $filename = request()->route()->parameters()['path'];

        // The route only ever captures the bare filename (see
        // field-type.blade.php's own download link), not the full stored
        // path — which now varies by who saved it (config/{name} for the
        // global row, tenants/{id}/configuration/{name} for a tenant's
        // own), so the matching row is found by suffix rather than by
        // reconstructing one fixed prefix.
        $config = $this->configurationRepository
            ->findWhere([['value', 'like', '%configuration/'.$filename]])
            ->first();

        if (! $config) {
            abort(404);
        }

        $tenantId = CurrentTenant::id();

        $belongsToAnotherTenant = $config->tenant_id !== null
            && $tenantId !== null
            && $config->tenant_id != $tenantId;

        if ($belongsToAnotherTenant) {
            abort(404);
        }

        return Storage::download($config->value);
    }

    /**
     * Search for configurations.
     */
    public function search(): JsonResponse
    {
        $results = $this->configurationRepository->search(
            system_config()->getItems(),
            request()->query('query')
        );

        return new JsonResponse([
            'data' => $results,
        ]);
    }
}
