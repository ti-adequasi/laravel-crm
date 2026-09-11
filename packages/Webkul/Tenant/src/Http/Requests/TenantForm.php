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
     */
    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|alpha_dash|unique:tenants,code,'.($tenantId ?? 'NULL').',id',
            'is_active' => 'nullable|boolean',
        ];
    }
}
