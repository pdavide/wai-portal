<?php

namespace App\Transformers;

use App\Enums\CredentialPermission;
use App\Enums\UserPermission;
use App\Models\Website;
use League\Fractal\TransformerAbstract;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * Website permissions transformer.
 */
class WebsitesPermissionsTransformer extends TransformerAbstract
{
    /**
     * Transform the website permission for datatable.
     *
     * @param Website $website the website
     *
     * @return array the response
     */
    public function transform(Website $website): array
    {
        $currentRequest = request();
        $publicAdministration = $currentRequest->route('publicAdministration', current_public_administration());
        $websiteUrlLink = array_key_exists('scheme', parse_url($website->url))
            ? e($website->url)
            : 'http://' . e($website->url);

        return Bouncer::scope()->onceTo($publicAdministration->id, function () use ($website, $websiteUrlLink, $currentRequest) {
            $user = $currentRequest->route('user');
            $readOnly = $currentRequest->has('readOnly');
            $oldPermissions = $currentRequest->query('oldPermissions');
            $canRead = !is_array($oldPermissions) && optional($user)->can(UserPermission::READ_ANALYTICS, $website);
            $canManageOrWrite = !is_array($oldPermissions) && optional($user)->can(UserPermission::MANAGE_ANALYTICS, $website);
            $websiteId = $website->id;

            $isCredentialPermissionsData = 'api-credentials.websites.permissions' === $currentRequest->route()->getName();

            if ($isCredentialPermissionsData) {
                $oldPermissions = $currentRequest->query('oldCredentialPermissions');
                $credentialPermissions = optional($currentRequest->route('credential'))->permissions;
                $canRead = !is_array($oldPermissions)
                    && $this->hasCredentialPermission($website->analytics_id, CredentialPermission::READ, $credentialPermissions);
                $canManageOrWrite = !is_array($oldPermissions)
                    && $this->hasCredentialPermission($website->analytics_id, CredentialPermission::WRITE, $credentialPermissions);
                $websiteId = $website->analytics_id;
            }

            $data = [
                'website_name' => [
                    'display' => implode('', [
                        '<span>',
                        '<strong>' . e($website->name) . '</strong>',
                        '<br>',
                        '<small><a href="' . $websiteUrlLink . '" target="_blank">' . e($website->url) . '</a></small>',
                        '</span>',
                    ]),
                    'raw' => e($website->name),
                ],
                'type' => $website->type->description,
                'status' => [
                    'display' => '<span class="badge website-status ' . strtolower($website->status->key) . '">' . strtoupper($website->status->description) . '</span>',
                    'raw' => $website->status->description,
                ],
            ];

            if ($readOnly) {
                $data['icons'] = [
                    [
                        'icon' => $canRead ? 'it-check-circle' : 'it-close-circle',
                        'color' => $canRead ? 'success' : 'danger',
                        'label' => $isCredentialPermissionsData
                            ? CredentialPermission::getDescription(CredentialPermission::READ)
                            : UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'icon' => $canManageOrWrite ? 'it-check-circle' : 'it-close-circle',
                        'color' => $canManageOrWrite ? 'success' : 'danger',
                        'label' => $isCredentialPermissionsData
                            ? CredentialPermission::getDescription(CredentialPermission::WRITE)
                            : UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ];
            } else {
                $data['toggles'] = [
                    [
                        'name' => 'permissions[' . $websiteId . '][]',
                        'value' => $isCredentialPermissionsData
                            ? CredentialPermission::READ
                            : UserPermission::READ_ANALYTICS,
                        'label' => $isCredentialPermissionsData
                            ? CredentialPermission::getDescription(CredentialPermission::READ)
                            : UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                        'checked' => in_array($isCredentialPermissionsData
                            ? CredentialPermission::READ
                            : UserPermission::READ_ANALYTICS, $oldPermissions[$websiteId] ?? []) || $canRead,
                        'dataAttributes' => [
                            'entity' => $websiteId,
                        ],
                    ],
                    [
                        'name' => 'permissions[' . $websiteId . '][]',
                        'value' => $isCredentialPermissionsData
                            ? CredentialPermission::WRITE
                            : UserPermission::MANAGE_ANALYTICS,
                        'label' => $isCredentialPermissionsData
                            ? CredentialPermission::getDescription(CredentialPermission::WRITE)
                            : UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                        'checked' => in_array($isCredentialPermissionsData
                            ? CredentialPermission::WRITE
                            : UserPermission::MANAGE_ANALYTICS, $oldCredentialPermission[$websiteId] ?? []) || $canManageOrWrite,
                        'dataAttributes' => [
                            'entity' => $websiteId,
                        ],
                    ],
                ];
            }

            return $data;
        });
    }

    /**
     * Check wether the specified website has the specified permission type in the passed credential permissions array.
     *
     * @param int $websiteId The website ID
     * @param array|null $credentialPermissions The Credential permissions
     * @param string $permissionType The permission Type
     *
     * @return bool Whether has or doesn't have a permission
     */
    protected function hasCredentialPermission(int $websiteId, string $permissionType, ?array $credentialPermissions): bool
    {
        if (!is_array($credentialPermissions)) {
            return false;
        }

        $websiteIds = array_column($credentialPermissions, 'id');
        $websitePermissionIndex = array_search($websiteId, $websiteIds);

        if (false === $websitePermissionIndex) {
            return false;
        }

        $websitePermissions = $credentialPermissions[$websitePermissionIndex];

        return array_key_exists('permission', $websitePermissions) && str_contains($websitePermissions['permission'], $permissionType);
    }
}
