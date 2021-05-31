<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Update credential request.
 */
class UpdateCredentialRequest extends StoreCredentialsRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array the validation rules
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $credential = $this->route('credential');

        $rules['credential_name'] = [
            'required',
            'min:3',
            'max:255',
            Rule::unique('credentials', 'client_name')->ignore($credential->id),
        ];

        return $rules;
    }
}
