<?php

namespace Webkul\Pbx\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PbxCallOriginateForm extends FormRequest
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
     * `phone` is validated here only for presence/shape as a string — the
     * actual Brazilian-number validation is PhoneNumberNormalizer's job
     * (called from PbxCallService::originate()), since "is this string
     * non-empty" and "does this resolve to a real national number" are
     * different concerns with different, clearer error messages.
     */
    public function rules(): array
    {
        return [
            'lead_id' => 'required|integer',
            'person_id' => 'nullable|integer',
            'phone' => 'required|string|max:30',
        ];
    }
}
