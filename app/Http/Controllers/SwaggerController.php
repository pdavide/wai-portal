<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Models\Credential;
use App\Models\PublicAdministration;
use App\Traits\HasRoleAwareUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SwaggerController extends Controller
{
    use HasRoleAwareUrls;

    /**
     * Display the Swagger.
     *
     * @param Request $request The request
     * @param PublicAdministration $publicAdministration The Public Administration
     *
     * @return View The view
     */
    public function index(Request $request, PublicAdministration $publicAdministration): View
    {
        $user = $request->user();
        if ($user->publicAdministrations->isEmpty() && $user->cannot(UserPermission::ACCESS_ADMIN_AREA)) {
            return redirect()->route('websites.index');
        }

        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $roleAwareUrls = $this->getRoleAwareUrlArray([
            'credentials' => 'api-credentials.index',
        ], [], $currentPublicAdministration);

        $credentials = Credential::where('public_administration_id', $currentPublicAdministration->id)->get()
            ->filter(function ($credential) {
                return 'admin' === $credential->type;
            });
        $hasCredentials = 0 !== count($credentials);

        $config = [
            'currentEnvironment' => app()->environment(),
            'credentialsList' => $credentials,
            'hascredentials' => $hasCredentials,
        ];

        return view('pages.swagger')->with($roleAwareUrls)->with($config);
    }

    /**
     * Api Swagger Specifications.
     *
     * @return JsonResponse The JsonResponse
     */
    public function apiSpecification(): JsonResponse
    {
        $path = resource_path('data/api.json');

        if (!is_file($path) || !is_readable($path)) {
            return response()
                ->json(['error' => 'API configuration file not readable'], 500);
        }

        $data = json_decode(file_get_contents($path));
        $apiUrl = config('kong-service.api_url');
        $apiVersion = config('app.api_version');
        $basePath = config('kong-service.portal_base_path');

        $data->servers = [
            [
                'url' => implode('/', array_filter([
                    $apiUrl,
                    $basePath,
                    $apiVersion,
                ])),
                'description' => 'API Gateway [WAI portal]',
            ],
        ];

        $data->components
            ->securitySchemes
            ->oAuth
            ->flows
            ->clientCredentials
            ->tokenUrl = $apiUrl . '/portal/oauth2/token';

        return response()
            ->json($data, 200);
    }
}
