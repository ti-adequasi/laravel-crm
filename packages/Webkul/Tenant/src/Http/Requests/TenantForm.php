<?php

namespace Webkul\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantForm extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The admin_* fields provision the tenant's first user, alongside a
     * default pipeline and role (see TenantController::store()) — required
     * only on create; editing a tenant never touches its users here (see
     * the separate "add another user" flow, admin.tenant.users.store).
     */
    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|alpha_dash|unique:tenants,code,'.($tenantId ?? 'NULL').',id',
            'is_active' => 'nullable|boolean',
            'admin_name' => $tenantId ? 'nullable' : 'required|string|max:255',
            'admin_email' => $tenantId ? 'nullable' : 'required|email|unique:users,email',
            'admin_password' => $tenantId ? 'nullable' : 'required|string|min:6',
            // Matches the house convention used by Settings > Users
            // (confirm_password/same:password), not Laravel's own
            // confirmed rule (which expects a *_confirmation suffix) —
            // the client-side vee-validate rule in the view is written
            // to match this same shape.
            'admin_confirm_password' => $tenantId ? 'nullable' : 'required_with:admin_password|same:admin_password',
        ];
    }
}
