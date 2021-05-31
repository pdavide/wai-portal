<?php

namespace App\Traits;

use App\Models\Credential;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Transformers\UserArrayTransformer;
use App\Transformers\WebsiteArrayTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Website and user responses.
 */
trait SendsResponse
{
    /**
     * Error codes to be used in responses.
     *
     * @var array the error codes
     */
    public $responseErrorCodes = [
        User::class => 1,
        Website::class => 2,
        Credential::class => 3,
    ];

    /**
     * Error names to be used in responses.
     *
     * @var array the error names
     */
    public $responseErrorNames = [
        400 => 'bad_request',
        500 => 'internal_server_error',
    ];

    /**
     * Returns a success response for the specified user.
     *
     * @param User $user the user
     * @param PublicAdministration|null $publicAdministration the public administration the user belongs to
     * @param array|null $notification the notification text to be sent if needed [ignored if the current request expects json]
     * @param string $redirectUrl the url to redirect to, if needed [ignored if the current request expects json]
     * @param int|null $code the http code for the response, defaults to 200 [ignored if the current request doesn't expects json]
     * @param array|null $headers additional http header to send [ignored if the current request doesn't expects json]
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function userResponse(User $user, ?PublicAdministration $publicAdministration = null, ?array $notification = [], ?string $redirectUrl = null, ?int $code = 200, ?array $headers = [])
    {
        $requestExpectsJson = request()->expectsJson();
        $userStatus = $user->status;
        $userTrashed = $user->trashed();

        if (!empty($publicAdministration)) {
            $userStatus = $user->getStatusforPublicAdministration($publicAdministration);
        }

        if ($requestExpectsJson) {
            $jsonResponse = (new UserArrayTransformer())->transform($user, $publicAdministration);

            if (!request()->is('api/*')) {
                $jsonResponse = [];
                $jsonResponse['result'] = 'ok';
                $jsonResponse['id'] = $user->uuid;
                $jsonResponse['user_name'] = e($user->full_name);
                $jsonResponse['status'] = $userStatus->key ?? null;
                $jsonResponse['status_description'] = $userStatus->description ?? null;
                $jsonResponse['trashed'] = $userTrashed;
                $jsonResponse['administration'] = $publicAdministration->name ?? null;
            }
        }

        $redirectResponse = is_null($redirectUrl) ? back() : redirect()->to($redirectUrl);
        if (!empty($notification)) {
            $redirectResponse = $redirectResponse->withNotification(array_merge([
                'status' => 'success',
                'icon' => 'it-check-circle',
            ], $notification));
        }

        return $requestExpectsJson
            ? response()->json($jsonResponse, $code)->withHeaders($headers)
            : $redirectResponse->withHeaders($headers);
    }

    /**
     * Returns a success response for the specified website.
     *
     * @param Website $website the website
     * @param array $notification the notifications
     * @param string $redirectUrl The url to redirect the user to
     * @param int $code The status code
     * @param array $headers The headers
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function websiteResponse(Website $website, ?array $notification = [], ?string $redirectUrl = null, ?int $code = 200, ?array $headers = [])
    {
        $requestExpectsJson = request()->expectsJson();

        if ($requestExpectsJson) {
            $jsonResponse = (new WebsiteArrayTransformer())->transform($website);

            if (!request()->is('api/*')) {
                $jsonResponse['result'] = 'ok';
                $jsonResponse['id'] = $website->slug;
                $jsonResponse['website_name'] = e($website->name);
                $jsonResponse['status'] = $website->status->key;
                $jsonResponse['status_description'] = $website->status->description;
                $jsonResponse['trashed'] = $website->trashed();
            }
        }

        $redirectResponse = is_null($redirectUrl) ? back() : redirect()->to($redirectUrl);
        if (!empty($notification)) {
            $redirectResponse = $redirectResponse->withNotification(array_merge([
                'status' => 'success',
                'icon' => 'it-check-circle',
            ], $notification));
        }

        return $requestExpectsJson
            ? response()->json($jsonResponse, $code)->withHeaders($headers)
            : $redirectResponse->withHeaders($headers);
    }

    /**
     * Returns a success response for the specified public administration.
     *
     * @param PublicAdministration $website the website
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function publicAdministrationResponse(PublicAdministration $publicAdministration)
    {
        return request()->expectsJson()
            ? response()->json([
                'result' => 'ok',
                'id' => $publicAdministration->id,
                'name' => e($publicAdministration->name),
                'status' => $publicAdministration->status->key,
                'status_description' => $publicAdministration->status->description,
                'ipa_code' => $publicAdministration->ipa_code,
            ])
            : back()->withNotification([
                'title' => __('Pubblica amministrazione modificata'),
                'message' => __("L'invito alla pubblica amministrazione :pa è stato confermato.", ['pa' => '<strong>' . e($publicAdministration->name) . '</strong>']),
                'status' => 'info',
                'icon' => 'it-info-circle',
            ]);
    }

    /**
     * Returns an not modified response.
     *
     * @param array $headers The headers
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function notModifiedResponse(?array $headers = [])
    {
        // Note: "Location" header must not be sent to the browser to avoid redirects
        if (!request()->is('api/*')) {
            $headers = [];
        }

        return request()->expectsJson()
            ? response()->json(null, 303)->withHeaders($headers)
            : back(303)->withNotification([
                'title' => __('operazione non effettuata'),
                'message' => __('La richiesta non ha determinato cambiamenti nello stato.'),
                'status' => 'info',
                'icon' => 'it-info-circle',
            ]);
    }

    /**
     * Returns a success response for the specified credential.
     *
     * @param Credential $credential the credential
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function credentialResponse(Credential $credential)
    {
        return request()->expectsJson()
            ? response()->json([
                'result' => 'ok',
                'id' => $credential->consumer_id,
                'credential_name' => e($credential->client_name),
                'status' => 200,
            ])
            : back()->withNotification([
                'title' => __('credenziale modificata'),
                'message' => __('Il sito web :website è stato eliminato.', ['website' => '<strong>' . e($credential->client_name) . '</strong>']),
                'status' => 'info',
                'icon' => 'it-info-circle',
            ]);
    }

    /**
     * Returns an error response with the specified parameters.
     *
     * @param string $message the error message
     * @param string $code the error code
     * @param int $httpStatusCode the http status code
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    protected function errorResponse(string $message, string $code, int $httpStatusCode)
    {
        $requestExpectsJson = request()->expectsJson();

        if ($requestExpectsJson) {
            $jsonResponse['error'] = $this->getErrorName($httpStatusCode);
            $jsonResponse['error_description'] = $message;
            $jsonResponse['error_code'] = $code;

            if (!request()->is('api/*')) {
                $jsonResponse['result'] = 'error';
                $jsonResponse['message'] = $message;
            }
        }

        $redirectResponse = back()->withNotification([
            'title' => __('errore del server'),
            'message' => implode("\n", [
                __('Si è verificato un errore relativamente alla tua richiesta.'),
                __('Puoi riprovare più tardi o :contact_support.', ['contact_support' => '<a href="' . route('contacts') . '">' . __('contattare il supporto tecnico') . '</a>']),
            ]),
            'status' => 'error',
            'icon' => 'it-close-circle',
        ]);

        return $requestExpectsJson
            ? response()->json($jsonResponse, $httpStatusCode)
            : $redirectResponse;
    }

    /**
     * Get an error response name for the provided http staus code.
     *
     * @param int $httpStatusCode the http status code
     *
     * @return string the response error name
     */
    protected function getErrorName(int $httpStatusCode): string
    {
        return $this->responseErrorNames[$httpStatusCode] ?? 'generic_error';
    }

    /**
     * Get an error response code for the provided class.
     *
     * @param string $class the class name
     *
     * @return int the response error code
     */
    protected function getErrorCode(string $class): int
    {
        return $this->responseErrorCodes[$class] ?? 99;
    }
}
