<?php

namespace Webkul\Tenant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Tenant\DataGrids\TenantDataGrid;
use Webkul\Tenant\Http\Requests\TenantForm;
use Webkul\Tenant\Models\Tenant;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Repositories\RoleRepository;
use Webkul\User\Repositories\UserRepository;

class TenantController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected PipelineRepository $pipelineRepository,
        protected RoleRepository $roleRepository,
        protected UserRepository $userRepository,
    ) {}

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
     *
     * A brand-new tenant is unusable through the Leads screens without its
     * own pipeline (with a stage) and its own role — the pre-existing
     * pipeline/role rows all predate multi-tenancy and carry tenant_id
     * NULL, invisible to a tenant-scoped user the moment one exists (see
     * tests/Feature/TenantScopingTest.php's own helpers, which hit this
     * exact wall while proving Phase 2.2). Provisioning all of it —
     * tenant, pipeline, stage, role, first user — atomically here is what
     * actually closes that gap, instead of leaving it as a manual
     * follow-up step.
     */
    public function store(TenantForm $request): RedirectResponse
    {
        $data = $request->validated();

        $data['is_active'] = $request->has('is_active');

        Event::dispatch('tenant.create.before');

        $tenant = DB::transaction(function () use ($data) {
            $tenant = $this->tenantRepository->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'],
            ]);

            $this->provisionDefaultsFor($tenant, $data['admin_name'], $data['admin_email'], $data['admin_password']);

            return $tenant;
        });

        Event::dispatch('tenant.create.after', $tenant);

        session()->flash('success', trans('tenant::app.index.create-success'));

        return redirect()->route('admin.tenant.index');
    }

    /**
     * Create the default pipeline (with a starter set of stages), the
     * tenant's own administrator role, and its first user — everything a
     * tenant needs to actually log in and use the Leads screens.
     */
    protected function provisionDefaultsFor(Tenant $tenant, string $adminName, string $adminEmail, string $adminPassword): void
    {
        // PipelineRepository::create() creates its stages itself, reading
        // them from a 'stages' key in this same array (see its own
        // implementation) — passing them as a separate stageRepository
        // call bypasses that contract and crashes on the missing key.
        //
        // lead_pipelines.name also carries a *global* unique constraint
        // (not scoped by tenant_id — that column didn't exist when it was
        // added), so a literal, non-interpolated default name would
        // collide starting with the second tenant ever created. The
        // tenant's own name makes it unique in the common case; two
        // tenants sharing an identical display name is an accepted edge
        // case here, same as the role name below.
        $pipeline = $this->pipelineRepository->create([
            'tenant_id' => $tenant->id,
            'name' => trans('tenant::app.create.default-pipeline-name', ['tenant' => $tenant->name]),
            'rotten_days' => 30,
            'is_default' => true,
            'stages' => [
                ['tenant_id' => $tenant->id, 'code' => 'new', 'name' => trans('tenant::app.create.default-stage-new'), 'probability' => 25, 'sort_order' => 1],
                ['tenant_id' => $tenant->id, 'code' => 'negotiation', 'name' => trans('tenant::app.create.default-stage-negotiation'), 'probability' => 50, 'sort_order' => 2],
                ['tenant_id' => $tenant->id, 'code' => 'won', 'name' => trans('tenant::app.create.default-stage-won'), 'probability' => 100, 'sort_order' => 3],
            ],
        ]);

        $role = $this->roleRepository->create([
            'tenant_id' => $tenant->id,
            'name' => trans('tenant::app.create.default-role-name', ['tenant' => $tenant->name]),
            'permission_type' => 'all',
            'permissions' => [],
        ]);

        $this->userRepository->create([
            'tenant_id' => $tenant->id,
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => bcrypt($adminPassword),
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $tenant = $this->tenantRepository->findOrFail($id);

        $tenantUsers = $this->userRepository->findWhere(['tenant_id' => $tenant->id]);

        return view('tenant::edit', compact('tenant', 'tenantUsers'));
    }

    /**
     * Add another user to an existing tenant, reusing the administrator
     * role provisioned when the tenant was created. A tenant onboarded
     * before this role existed (or whose role was since deleted) has
     * nothing to attach a new user to — handled explicitly rather than
     * assigning some unrelated role by accident.
     */
    public function storeUser(int $id): RedirectResponse
    {
        $tenant = $this->tenantRepository->findOrFail($id);

        // Namespaced (new_user_*) rather than the plain name/email/password
        // used elsewhere in this codebase's user forms — this form shares
        // a page with the tenant's own edit form, and a plain "name" field
        // in both collided: same DOM attribute, cross-talking client-side
        // validation state, and a `fill()`-style interaction landing in
        // the wrong input.
        $validated = request()->validate([
            'new_user_name' => 'required|string|max:255',
            'new_user_email' => 'required|email|unique:users,email',
            'new_user_password' => 'required|string|min:6',
            'new_user_confirm_password' => 'required_with:new_user_password|same:new_user_password',
        ]);

        $role = $this->roleRepository->findOneWhere(['tenant_id' => $tenant->id]);

        if (! $role) {
            session()->flash('error', trans('tenant::app.edit.no-role-error'));

            return redirect()->route('admin.tenant.edit', $tenant->id);
        }

        Event::dispatch('tenant.user.create.before', $tenant);

        $user = $this->userRepository->create([
            'tenant_id' => $tenant->id,
            'name' => $validated['new_user_name'],
            'email' => $validated['new_user_email'],
            'password' => bcrypt($validated['new_user_password']),
            'status' => 1,
            'role_id' => $role->id,
        ]);

        Event::dispatch('tenant.user.create.after', $user);

        session()->flash('success', trans('tenant::app.edit.user-create-success'));

        return redirect()->route('admin.tenant.edit', $tenant->id);
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
