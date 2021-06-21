<?php

use App\Models\PublicAdministration;

if (!function_exists('current_public_administration')) {
    /**
     * Get the public administration corresponding to the current selected tenant.
     *
     * @return PublicAdministration|null the PublicAdministration found or null if not found
     */
    function current_public_administration(): ?PublicAdministration
    {
        $user = auth()->user();
        if ($user) {
            return $user->publicAdministrations()->where('id', session('tenant_id'))->first();
        }

        return null;
    }
}
