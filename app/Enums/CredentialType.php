<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * Credential types.
 */
final class CredentialType extends Enum implements LocalizedEnum
{
    /**
     * Admin credential.
     */
    public const ADMIN = 'admin';

    /**
     * Analytics credential.
     */
    public const ANALYTICS = 'analytics';
}
