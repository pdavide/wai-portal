<?php

namespace App\Http\Requests;

use App\Enums\CredentialPermission;
use App\Enums\CredentialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Store credential request.
 */
class StoreCredentialsRequest extends FormRequest
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
        return [
            'credential_name' => 'required|unique:credentials,client_name|min:3|max:255',
            'type' => 'required',
            'permissions' => 'required_if:type,' . CredentialType::ANALYTICS . '|array',
            'permissions.*' => 'array',
            'permissions.*.*' => Rule::in([CredentialPermission::WRITE, CredentialPermission::READ]),
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator the validator instance
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (is_array($this->input('permissions')) && !$this->checkWebsitesAnalyticsIds($this->input('permissions'))) {
                $validator->errors()->add('permissions', __('validation.errors.permissions'));
            }
        });
    }

    /**
     * Check whether the websitesPermission array contains keys belonging to
     * websites of the current selected public administration.
     *
     * @param array $websitesPermissions The websitesPermissions array
     *
     * @return bool true if the provided user permissions contains keys belonging to websites in the current public administration
     */
    protected function checkWebsitesAnalyticsIds(array $websitesPermissions): bool
    {
        $currentPublicAdministration = $this->is('api/*')
            ? request()->publicAdministrationFromToken
            : current_public_administration();

        return empty(
            array_diff(
                array_keys($websitesPermissions),
                request()->route('publicAdministration', $currentPublicAdministration)->websites->pluck('analytics_id')->all()
            )
        );
    }
}
