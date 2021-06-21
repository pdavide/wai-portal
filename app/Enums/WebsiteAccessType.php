<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * The website access type for the Analytics Service.
 */
final class WebsiteAccessType extends Enum implements LocalizedEnum
{
    /**
     * No access constant.
     */
    public const NO_ACCESS = 0;

    /**
     * Read-only access constant.
     */
    public const VIEW = 1;

    /**
     * Manage analytics access constant.
     */
    public const WRITE = 2;

    /**
     * Website admin access constant.
     */
    public const ADMIN = 3;

    /**
     * Map user permissions to website access types.
     */
    private const PERMISSIONS_TO_ACCESS_MAPPINGS = [
        UserPermission::NO_ACCESS => WebsiteAccessType::NO_ACCESS,
        UserPermission::READ_ANALYTICS => WebsiteAccessType::VIEW,
        UserPermission::MANAGE_ANALYTICS => WebsiteAccessType::WRITE,
    ];

    /**
     * Get the website access type corresponding to the given user permission.
     *
     * @param string $userPermission the user permission
     *
     * @return int the user permission key value
     */
    public static function fromUserPermission(string $userPermission): int
    {
        return self::PERMISSIONS_TO_ACCESS_MAPPINGS[$userPermission];
    }
}
