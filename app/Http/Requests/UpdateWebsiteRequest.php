<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Enums\WebsiteType;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Update website request.
 */
class UpdateWebsiteRequest extends StoreWebsiteRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array the validation rules
     */
    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->route('website')->type->is(WebsiteType::INSTITUTIONAL)) {
            $rules['type'] = [
                'required',
                Rule::in([$this->route('website')->type->description]),
            ];
            $rules['url'] = [
                'required',
                Rule::in($this->route('website')->url),
            ];
        } else {
            $rules['url'] = [
                'required',
                Rule::unique('websites')->ignore($this->route('website')->id),
                'url',
                'max:255',
            ];
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator the validator reference
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator) {
            $affectedUsers = $this->checkLastWebsiteForUsers($this->route('website'), $this->input('permissions'));
            if (!empty($affectedUsers)) {
                $affectedUsers->map(function ($affectedUser) use ($validator) {
                    $validator->errors()->add('permissions', __('validation.errors.last_website_enabled', ['user' => $affectedUser->info]));
                });
            }
        });
    }

    /**
     * Check whether the provided website is the last enabled website for users
     * not listed in the permissions parameter.
     *
     * @param Website $website The website to check
     * @param array|null $permissions The users enabled array
     *
     * @return Collection The collection of users for whom the website is the last enabled one
     */
    protected function checkLastWebsiteForUsers(Website $website, ?array $permissions): Collection
    {
        return $website->getEnabledNonAdministratorUsers()->whereNotInStrict('id', $permissions ?? [])->filter(function ($user) {
            return 1 === $user->abilities->where('name', UserPermission::READ_ANALYTICS)->groupBy('entity_id')->count();
        });
    }
}
