<?php

namespace App\Enums;

use App\Traits\HasEnumLongDescription;
use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * Credential permissions.
 */
final class CredentialPermission extends Enum implements LocalizedEnum
{
    use HasEnumLongDescription;

    /**
     * Read permission far analytics credential.
     */
    public const READ = 'R';

    /**
     * Write permission far analytics credential.
     */
    public const WRITE = 'W';
}
