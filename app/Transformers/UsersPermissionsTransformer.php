<?php

namespace App\Transformers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use League\Fractal\TransformerAbstract;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * User permissions transformer.
 */
class UsersPermissionsTransformer extends TransformerAbstract
{
    /**
     * Transform the user permission for datatable.
     *
     * @param User $user the user
     *
     * @return array the response
     */
    public function transform(User $user): array
    {
        $currentRequest = request();
        $publicAdministration = $currentRequest->route('publicAdministration', current_public_administration());

        return Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user, $currentRequest) {
            $website = $currentRequest->route('website');
            $readOnly = $currentRequest->has('readOnly');
            $oldPermissions = $currentRequest->query('oldPermissions');
            $isAdmin = $user->isAn(UserRole::ADMIN);
            $canRead = $isAdmin || !is_array($oldPermissions) && isset($website) && $user->can(UserPermission::READ_ANALYTICS, $website);
            $canManage = $isAdmin || !is_array($oldPermissions) && isset($website) && $user->can(UserPermission::MANAGE_ANALYTICS, $website);

            $data = [
                'name' => [
                    'display' => implode('', [
                        '<div class="d-flex align-items-center">',
                        '<div class="avatar size-lg mr-2">',
                        implode('', [
                            '<img src="https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?d=' . ($user->email ? 'identicon' : 'mp') . '"',
                            'alt="' . e($user->full_name) . '">',
                        ]),
                        '</div>',
                        '<span class="user">',
                        '<strong>' . e($user->full_name) . '</strong>',
                        '<br>',
                        '<small class="text-muted text-uppercase">' . $user->all_role_names . '</small>',
                        '</span>',
                        '</div>',
                    ]),
                    'raw' => e($user->full_name),
                ],
                'email' => e($user->email),
                'status' => $user->status->description,
            ];

            if ($readOnly) {
                $data['icons'] = [
                    [
                        'icon' => $canRead ? 'it-check-circle' : 'it-close-circle',
                        'color' => $canRead ? 'success' : 'danger',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'icon' => $canManage ? 'it-check-circle' : 'it-close-circle',
                        'color' => $canManage ? 'success' : 'danger',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ];
            } else {
                $data['toggles'] = [
                    [
                        'name' => 'permissions[' . $user->id . '][]',
                        'value' => UserPermission::READ_ANALYTICS,
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                        'disabled' => $user->isAn(UserRole::ADMIN),
                        'checked' => in_array(UserPermission::READ_ANALYTICS, $oldPermissions[$user->id] ?? []) || $canRead,
                        'dataAttributes' => [
                            'entity' => $user->id,
                        ],
                    ],
                    [
                        'name' => 'permissions[' . $user->id . '][]',
                        'value' => UserPermission::MANAGE_ANALYTICS,
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                        'disabled' => $user->isAn(UserRole::ADMIN),
                        'checked' => in_array(UserPermission::MANAGE_ANALYTICS, $oldPermissions[$user->id] ?? []) || $canManage,
                        'dataAttributes' => [
                            'entity' => $user->id,
                        ],
                    ],
                ];
            }

            return $data;
        });
    }
}
