<?php

namespace App\Http\Middleware;

use App\Models\Credential;
use App\Models\User;
use App\Models\Website;
use App\Traits\SendsResponse;
use Closure;

class AuthenticateApi
{
    use SendsResponse;

    /**
     * Check whether the session has a tenant selected for the current request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $consumerId = $request->header('X-Consumer-Id');
        $customId = $request->header('X-Consumer-Custom-Id');

        if (null === $customId || null === $consumerId) {
            return response()->json(['error' => '500 Internal Server Error'], 500);
        }

        $customId = json_decode($customId);
        $credentialType = '';

        if (property_exists($customId, 'type')) {
            $credentialType = $customId->type;
        }

        if ('admin' !== $credentialType) {
            return response()->json([
                'error' => 'forbidden',
                'error_description' => 'Access to the requested resource is forbidden',
            ], 403);
        }

        $currentCredential = Credential::getCredentialFromConsumerId($consumerId);
        $publicAdministration = $currentCredential->publicAdministration->first();

        $requestApiParams = ['publicAdministrationFromToken' => $publicAdministration];

        if ($request->fn) {
            $user = User::findNotSuperAdminByFiscalNumber($request->fn);

            if (!$user || !$user->publicAdministrationsWithSuspended->contains($publicAdministration)) {
                return response()->json([
                    'error' => 'not_found',
                    'error_description' => 'The requested resource cannot be found on this server',
                ], 404);
            }
            $requestApiParams['userFromFiscalNumber'] = $user;
        }

        if ($request->website instanceof Website
            && $request->website->publicAdministration->id !== $publicAdministration->id) {
            return response()->json([
                'error' => 'not_found',
                'error_description' => 'The requested resource cannot be found on this server',
            ], 404);
        }

        $request->merge($requestApiParams);

        // use english for api responses
        app()->setLocale('en');

        return $next($request);
    }
}
