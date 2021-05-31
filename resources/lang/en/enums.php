<?php

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use App\Enums\Logs\JobType;
use App\Enums\CredentialPermission;
use App\Enums\CredentialType;
use App\Enums\PublicAdministrationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;

return [

    PublicAdministrationStatus::class => [
        PublicAdministrationStatus::PENDING => 'pending',
        PublicAdministrationStatus::ACTIVE => 'active',
        PublicAdministrationStatus::SUSPENDED => 'suspended',
    ],

    UserPermission::class => [
        UserPermission::ACCESS_ADMIN_AREA => 'access to admin area',
        UserPermission::MANAGE_USERS => 'manage users',
        UserPermission::MANAGE_WEBSITES => 'manage websites',
        UserPermission::VIEW_LOGS => 'view logs',
        UserPermission::MANAGE_ANALYTICS => [
            'short' => 'manage analytics',
            'long' => 'The management permission allows the user to change settings for the analytics data.',
        ],
        UserPermission::READ_ANALYTICS => [
            'short' => 'read analytics',
            'long' => 'The reading permission allows the user to view of all analytics data.',
        ],
        UserPermission::DO_NOTHING => 'no permissions',
    ],

    UserRole::class => [
        UserRole::SUPER_ADMIN => [
            'short' => 'super admin of ' . config('app.name_short'),
            'long' => 'The super administrator can manage all the data in ' . config('app.name') . '.',
        ],
        UserRole::ADMIN => [
            'short' => 'administrator',
            'long' => 'The administrator can manage all websites and users of his Public Administration.',
        ],
        UserRole::DELEGATED => [
            'short' => 'delegate',
            'long' => 'The delegate user can read or manage the analytics data according to the permissions assigned by the administrator.',
        ],
        UserRole::REGISTERED => [
            'short' => 'registered',
            'long' => 'The registered user has to continue the procedure by indicating his Public Administration.',
        ],
        UserRole::DELETED => [
            'short' => 'deleted',
            'long' => 'The deleted user does not have access to ' . config('app.name') . '.',
        ],
    ],

    UserStatus::class => [
        UserStatus::INVITED => [
            'short' => 'invited',
            'long' => 'The user has not yet accepted the invitation to ' . config('app.name') . '.',
        ],
        UserStatus::INACTIVE => [
            'short' => 'inactive',
            'long' => 'The user has not yet registered his Public Administratio on ' . config('app.name') . '.',
        ],
        UserStatus::PENDING => [
            'short' => 'pending',
            'long' => 'The user is waiting for the activation on ' . config('app.name') . '.',
        ],
        UserStatus::ACTIVE => [
            'short' => 'active',
            'long' => 'The user is active and can use the services provided by ' . config('app.name') . '.',
        ],
        UserStatus::SUSPENDED => [
            'short' => 'suspended',
            'long' => 'The user has been suspended and cannot use the services provided by ' . config('app.name') . '.',
        ],
    ],

    WebsiteStatus::class => [
        WebsiteStatus::PENDING => [
            'short' => 'pending',
            'long' => "The website isn't tracking traffic yet. 😕",
        ],
        WebsiteStatus::ACTIVE => [
            'short' => 'active',
            'long' => 'The website is now tracking traffic! 🎉',
        ],
        WebsiteStatus::ARCHIVED => [
            'short' => 'archived',
            'long' => 'The website has been archived. 🛑',
        ],
    ],

    WebsiteType::class => [
        WebsiteType::INSTITUTIONAL => 'institutional website',
        WebsiteType::INFORMATIONAL => 'informational or thematic website',
        WebsiteType::SERVICE => 'services website',
        WebsiteType::MOBILE => 'mobile application',
        WebsiteType::INSTITUTIONAL_PLAY => 'institutional website',
    ],

    WebsiteAccessType::class => [
        WebsiteAccessType::NO_ACCESS => 'no access',
        WebsiteAccessType::VIEW => 'read-only access',
        WebsiteAccessType::WRITE => 'manage analytics access',
        WebsiteAccessType::ADMIN => 'admin access',
    ],

    CredentialType::class => [
        CredentialType::ADMIN => 'admin',
        CredentialType::ANALYTICS => 'analytics',
    ],

    CredentialPermission::class => [
        CredentialPermission::READ => [
            'short' => 'read',
            'long' => "Read permission allows the query of the analytics API for read operations only.",
        ],
        CredentialPermission::WRITE => [
            'short' => 'write',
            'long' => "Write permission allows the query of the analytics API for both read and write operations.",
        ],
    ],

    EventType::class => [
        EventType::EXCEPTION => 'Error',
        EventType::ANALYTICS_LOGIN => 'Analytics Service Login',
        EventType::PENDING_WEBSITES_CHECK_COMPLETED => 'Pending websites check completed',
        EventType::TRACKING_WEBSITES_CHECK_COMPLETED => 'Website tracking check completed',
        EventType::UPDATE_PA_FROM_IPA_COMPLETED => 'IPA update completed',
        EventType::PUBLIC_ADMINISTRATION_REGISTERED => 'Public Administration registered',
        EventType::PUBLIC_ADMINISTRATION_ACTIVATED => 'Public Administration activated',
        EventType::PUBLIC_ADMINISTRATION_ACTIVATION_FAILED => 'Public Administration activation failed',
        EventType::PUBLIC_ADMINISTRATION_UPDATED => 'Public Administration updated',
        EventType::PUBLIC_ADMINISTRATION_PRIMARY_WEBSITE_CHANGED => 'Primary website changed',
        EventType::PUBLIC_ADMINISTRATION_PURGED => 'Public Administration removed',
        EventType::USER_LOGIN => 'User logged in',
        EventType::USER_LOGOUT => 'User logged out',
        EventType::USER_REGISTERED => 'User registered',
        EventType::USER_INVITED => 'User invited',
        EventType::USER_VERIFIED => 'User email verified',
        EventType::USER_ACTIVATED => 'User activated',
        EventType::USER_EMAIL_CHANGED => 'User email changed',
        EventType::USER_STATUS_CHANGED => 'User status changed',
        EventType::USER_SUSPENDED => 'User suspended',
        EventType::USER_REACTIVATED => 'User reactivated',
        EventType::USER_DELETED => 'User deleted',
        EventType::USER_RESTORED => 'User restored',
        EventType::USER_WEBSITE_ACCESS_CHANGED => 'User access level to website changed',
        EventType::WEBSITE_ADDED => 'Website added',
        EventType::WEBSITE_URL_CHANGED => 'Website URL changed',
        EventType::WEBSITE_ACTIVATED => 'Website activated',
        EventType::WEBSITE_STATUS_CHANGED => 'Website status updated',
        EventType::WEBSITE_ARCHIVING => 'Website scheduled for archiving',
        EventType::WEBSITE_ARCHIVED => 'Website archived',
        EventType::WEBSITE_UNARCHIVED => 'Website unarchived',
        EventType::WEBSITE_PURGING => 'Website scheduled for removing',
        EventType::WEBSITE_PURGED => 'Website removed',
        EventType::WEBSITE_DELETED => 'Website manually deleted',
        EventType::WEBSITE_RESTORED => 'Website restored',
        EventType::PRIMARY_WEBSITE_NOT_TRACKING => 'Primary website tracking not active',
        EventType::USERS_INDEXING_COMPLETED => 'Users index update completed',
        EventType::WEBSITES_INDEXING_COMPLETED => 'Websites index update completed',
        EventType::EXPIRED_USER_INVITATION_USED => 'Activation attempt using expired invitation',
        EventType::USER_PASSWORD_RESET_COMPLETED => 'User password reset completed',
        EventType::USER_UPDATED => 'User updated',
        EventType::WEBSITE_UPDATED => 'Website updated',
        EventType::CLOSED_BETA_WHITELIST_UPDATE_FAILED => 'Closed beta whitelist update failed',
        EventType::PURGE_PENDING_INVITATIONS_COMPLETED => 'Purge old pending invitations completed',
        EventType::ENVIRONMENT_RESET_COMPLETED => 'Environment reset completed',
        EventType::MAIL_SENT => 'Mail sent',
    ],

    ExceptionType::class => [
        ExceptionType::GENERIC => 'Not specified error',
        ExceptionType::ANALYTICS_ACCOUNT => 'Analytics Service authentication error',
        ExceptionType::ANALYTICS_SERVICE => 'Analytics Service error',
        ExceptionType::ANALYTICS_COMMAND => 'Analytics Service command error',
        ExceptionType::HTTP_CLIENT_ERROR => 'Client http error (4xx)',
        ExceptionType::SERVER_ERROR => 'Internal server error',
        ExceptionType::TENANT_SELECTION => 'Missing public administration selection error',
        ExceptionType::IPA_INDEX_SEARCH => 'IPA index search error',
        ExceptionType::REDIS_INDEX_SEARCH => 'Index search error',
        ExceptionType::INVALID_WEBSITE_STATUS => 'Invalid website status error',
        ExceptionType::INVALID_OPERATION => 'Invalid operation error',
        ExceptionType::INVALID_USER_STATUS => 'Invalid user status error',
        ExceptionType::EXPIRED_INVITATION_LINK_USAGE => 'Expired user invitation link used',
        ExceptionType::EXPIRED_VERIFICATION_LINK_USAGE => 'Expired user verification link used',
        ExceptionType::SINGLE_DIGITAL_GATEWAY => 'Single Digital Gateway Service error',
    ],

    JobType::class => [
        JobType::CLEAR_PASSWORD_TOKEN => 'Clear expired password token',
        JobType::UPDATE_PA_FROM_IPA => 'Update public administrations from IPA index',
        JobType::SEND_RESET_PASSWORD_TOKEN => 'Send password reset token',
        JobType::SEND_EMAIL_VERIFICATION_TOKEN => 'Send email verification token',
        JobType::PROCESS_PENDING_WEBSITES => 'Process pending websites',
        JobType::PROCESS_USERS_INDEX => 'Update users index',
        JobType::PROCESS_WEBSITES_INDEX => 'Update websites index',
        JobType::MONITOR_WEBSITES_TRACKING => 'Monitor websites tracking status',
        JobType::PURGE_PENDING_INVITATIONS => 'Purge old pending invitations',
        JobType::RESET_ENVIRONMENT => 'Environment reset',
    ],
];
