<?php

namespace Webkul\Pbx\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PbxSettingForm extends FormRequest
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
     * api_key is deliberately nullable here — the edit form never
     * re-displays a previously-saved key (it's encrypted and hidden), so
     * a blank submission means "keep the existing one", not "clear it".
     */
    public function rules(): array
    {
        return [
            'enabled' => 'nullable|boolean',
            'api_key' => 'nullable|string|max:255',
        ];
    }
}
