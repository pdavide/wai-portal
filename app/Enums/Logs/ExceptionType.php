<?php

namespace App\Enums\Logs;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * Exception types.
 */
class ExceptionType extends Enum implements LocalizedEnum
{
    /**
     * Generic exception.
     */
    public const GENERIC = 0;

    /**
     * Analytics Service account related exception.
     */
    public const ANALYTICS_ACCOUNT = 1;

    /**
     * Analytics Service related exception.
     */
    public const ANALYTICS_SERVICE = 2;

    /**
     * Analytics Service command related exception.
     */
    public const ANALYTICS_COMMAND = 3;

    /**
     * Client HTTP error.
     */
    public const HTTP_CLIENT_ERROR = 4;

    /**
     * Internal server error.
     */
    public const SERVER_ERROR = 5;

    /**
     * Tenant selection exception.
     */
    public const TENANT_SELECTION = 6;

    /**
     * IPA search exception.
     */
    public const IPA_INDEX_SEARCH = 7;

    /**
     * Redis index search exception.
     */
    public const REDIS_INDEX_SEARCH = 8;

    /**
     * Invalid website status related exception.
     */
    public const INVALID_WEBSITE_STATUS = 9;

    /**
     * Invalid operation related exception.
     */
    public const INVALID_OPERATION = 10;

    /**
     * Invalid user status related exception.
     */
    public const INVALID_USER_STATUS = 11;

    /**
     * Expired invitation URL related exception.
     */
    public const EXPIRED_INVITATION_LINK_USAGE = 12;

    /**
     * Expired verification URL related exception.
     */
    public const EXPIRED_VERIFICATION_LINK_USAGE = 13;

    /**
     * Error in single digital gateway api calls or payload validation.
     */
    public const SINGLE_DIGITAL_GATEWAY = 14;

    /**
     * API Gateway Service related exception.
     */
    public const API_GATEWAY_SERVICE = 15;
}
