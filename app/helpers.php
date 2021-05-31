<?php

use App\Models\PublicAdministration;
use App\Models\User;

if (!function_exists('current_public_administration')) {
    /**
     * Get the public administration corresponding to the current selected tenant.
     *
     * @return PublicAdministration|null the PublicAdministration found or null if not found
     */
    function current_public_administration(): ?PublicAdministration
    {
        $user = auth()->user();
        $tenantId = session('tenant_id');

        if ($user) {
            return $user->publicAdministrations()->where('id', $tenantId)->first();
        }

        return null;
    }
}

if (!function_exists('current_user_auth_token')) {
    /**
     * Get the Analytics Service authentication token for the current user.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     * @throws \App\Exceptions\AnalyticsServiceException if unable to contact the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command finishes with error
     *
     * @return string|null the user authentication token or null if unable to retrieve
     */
    function current_user_auth_token(): ?string
    {
        $user = auth()->user();
        if ($user) {
            return $user->getAnalyticsServiceAccountTokenAuth();
        }

        return null;
    }
}
