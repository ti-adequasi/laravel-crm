<?php

namespace Webkul\Tenant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Tenant\DataGrids\TenantDataGrid;
use Webkul\Tenant\Http\Requests\TenantForm;
use Webkul\Tenant\Repositories\TenantRepository;

class TenantController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected TenantRepository $tenantRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(TenantDataGrid::class)->process();
        }

        return view('tenant::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('tenant::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TenantForm $request): RedirectResponse
    {
        $data = $request->validated();

        $data['is_active'] = $request->has('is_active');

        Event::dispatch('tenant.create.before');

        $tenant = $this->tenantRepository->create($data);

        Event::dispatch('tenant.create.after', $tenant);

        session()->flash('success', trans('tenant::app.index.create-success'));

        return redirect()->route('admin.tenant.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $tenant = $this->tenantRepository->findOrFail($id);

        return view('tenant::edit', compact('tenant'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TenantForm $request, int $id): RedirectResponse
    {
        $data = $request->validated();

        $data['is_active'] = $request->has('is_active');

        Event::dispatch('tenant.update.before', $id);

        $tenant = $this->tenantRepository->update($data, $id);

        Event::dispatch('tenant.update.after', $tenant);

        session()->flash('success', trans('tenant::app.index.update-success'));

        return redirect()->route('admin.tenant.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * JSON, not a redirect — this is called via the DataGrid's own AJAX
     * delete action.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->tenantRepository->findOrFail($id);

        try {
            Event::dispatch('tenant.delete.before', $id);

            $this->tenantRepository->delete($id);

            Event::dispatch('tenant.delete.after', $id);

            return response()->json([
                'message' => trans('tenant::app.index.delete-success'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => trans('tenant::app.index.delete-failed'),
            ], 400);
        }
    }
}
