<?php

namespace App\Transformers;

use App\Enums\UserPermission;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Models\Website;
use League\Fractal\TransformerAbstract;
use Silber\Bouncer\BouncerFacade as Bouncer;

class WebsiteArrayTransformer extends TransformerAbstract
{
    /**
     * Transform website data for API responces.
     *
     * @param Website $website The website
     *
     * @return array The responce
     */
    public function transform(Website $website): array
    {
        $publicAdministration = $website->publicAdministration;
        $usersPermissions = Bouncer::scope()->onceTo($publicAdministration->id, function () use ($publicAdministration, $website) {
            return $publicAdministration->users->mapWithKeys(function ($user) use ($website) {
                $permission = [];
                if ($user->can(UserPermission::READ_ANALYTICS, $website)) {
                    array_push($permission, UserPermission::READ_ANALYTICS);
                }
                if ($user->can(UserPermission::MANAGE_ANALYTICS, $website)) {
                    array_push($permission, UserPermission::MANAGE_ANALYTICS);
                }

                return [$user->fiscal_number => $permission];
            });
        })->toArray();

        return [
            'name' => $website->name,
            'url' => $website->url,
            'slug' => $website->slug,
            'status' => WebsiteStatus::fromValue($website->status)->description,
            'type' => WebsiteType::fromValue($website->type)->description,
            'permissions' => $usersPermissions,
        ];
    }
}
