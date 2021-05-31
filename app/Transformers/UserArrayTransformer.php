<?php

namespace App\Transformers;

use App\Enums\UserPermission;
use App\Models\PublicAdministration;
use App\Models\User;
use League\Fractal\TransformerAbstract;
use Silber\Bouncer\BouncerFacade as Bouncer;

class UserArrayTransformer extends TransformerAbstract
{
    /**
     * Transform the user resources for json responses.
     *
     * @param User $user the user
     * @param PublicAdministration|null $publicAdministration the public administration the user belongs to
     *
     * @return array the response
     */
    public function transform(User $user, ?PublicAdministration $publicAdministration = null): array
    {
        $email = is_null($publicAdministration)
            ? $user->email
            : $user->getEmailforPublicAdministration($publicAdministration);
        $status = is_null($publicAdministration)
            ? $user->status
            : $user->getStatusforPublicAdministration($publicAdministration)->description ?? null;

        $websitesPermissions = null !== $publicAdministration
            ? Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user, $publicAdministration) {
                $user->role = $user->all_role_names;

                return $publicAdministration->websites->mapWithKeys(function ($website) use ($user) {
                    $permission = [];
                    if ($user->can(UserPermission::READ_ANALYTICS, $website)) {
                        array_push($permission, UserPermission::READ_ANALYTICS);
                    }
                    if ($user->can(UserPermission::MANAGE_ANALYTICS, $website)) {
                        array_push($permission, UserPermission::MANAGE_ANALYTICS);
                    }

                    return [$website->slug => $permission];
                });
            })->toArray()
            : [];

        return [
            'first_name' => $user->name,
            'family_name' => $user->family_name,
            'fiscal_number' => $user->fiscal_number,
            'email' => $email,
            'status' => $status,
            'permissions' => $websitesPermissions,
            'role' => $user->role,
        ];
    }
}
