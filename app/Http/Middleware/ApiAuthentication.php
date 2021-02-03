<?php

namespace App\Http\Middleware;

use App\Models\Credential;
use Closure;
use Illuminate\Session\Middleware\StartSession;

class ApiAuthentication extends StartSession
{
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

        if (null === $customId) {
            return response()->json($this->jsonError(1), 403);
        }

        $customId = json_decode($customId);
        $credentialType = $credentialSites = '';

        if (property_exists($customId, 'type') && property_exists($customId, 'siteId')) {
            $credentialType = $customId->type;
            $credentialSites = $customId->siteId;
        }

        if (null === $consumerId || 'admin' !== $credentialType || !is_array($credentialSites)) {
            print_r([$consumerId, $credentialType, $credentialSites]);

            return response()->json($this->jsonError(2), 403);
        }

        $credentials = new Credential();

        $selectCredential = $credentials->getCredentialFromConsumerId($consumerId);

        $publicAdministration = $selectCredential->publicAdministration()->first();

        $request->attributes->add(['publicAdministration' => $publicAdministration]);

        $website = $request->route()->parameter('website');

        if (null !== $website) {
            $column = array_column($credentialSites, 'id');
            if (!in_array($website->id, $column)) {
                return response()->json($this->jsonError(3), 401);
            }
        }

        return $next($request);
    }

    protected function jsonError($code)
    {
        return [
            'title' => 'insufficient permission err. ' . $code,
            'message' => 'You\'re not allowed to carry out this action',
            'type' => 'insufficient_permission',
        ];
    }
}
