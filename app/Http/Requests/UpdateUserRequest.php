<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Update user request.
 */
class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array the validation rules
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['emailPublicAdministrationUser'] = 'required|email:rfc,dns|max:75';

        $user = ('api.users.update' === $this->route()->getName())
          ? $this->userFromFiscalNumber
          : $this->route('user');

        if (!$user->status->is(UserStatus::INVITED)) {
            unset($rules['fiscal_number']);
        } else {
            $rules['fiscal_number'][] = Rule::unique('users')->ignore($user->id);
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator the validator instance
     */
    public function withValidator(Validator $validator): void
    {
        if (!$this->is('api/*')) {
            $publicAdministration = $this->user()->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $this->route('publicAdministration')
                : current_public_administration();
            $user = $this->route('user');
        } else {
            $publicAdministration = $this->publicAdministrationFromToken;
            $user = User::findNotSuperAdminByFiscalNumber($this->fn);
        }

        $validator->after(function (Validator $validator) use ($user) {
            if (User::where('email', $this->input('email'))->where('id', '<>', $user->id)->whereDoesntHave('roles', function ($query) {
                $query->where('name', UserRole::SUPER_ADMIN);
            })->get()->isNotEmpty()) {
                $validator->errors()->add('email', __('validation.unique', ['attribute' => __('validation.attributes.email')]));
            }
        });

        $validator->after(function (Validator $validator) use ($user, $publicAdministration) {
            if ($user->isTheLastActiveAdministratorOf($publicAdministration) && !$this->input('is_admin')) {
                $validator->errors()->add('is_admin', __('validation.errors.last_admin'));
            }
        });

        parent::withValidator($validator);
    }
}
