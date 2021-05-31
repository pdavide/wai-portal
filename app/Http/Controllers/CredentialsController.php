<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Exceptions\InvalidCredentialException;
use App\Http\Requests\StoreCredentialsRequest;
use App\Http\Requests\UpdateCredentialRequest;
use App\Models\Credential;
use App\Models\PublicAdministration;
use App\Models\Website;
use App\Traits\HasRoleAwareUrls;
use App\Traits\SendsResponse;
use App\Transformers\CredentialsTransformer;
use App\Transformers\WebsitesPermissionsTransformer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\DataTables;

class CredentialsController extends Controller
{
    use HasRoleAwareUrls;
    use SendsResponse;

    protected $clientService;

    public function __construct()
    {
        $this->clientService = app()->make('kong-client-service');
    }

    /**
     * Display the credentials list.
     *
     * @param PublicAdministration $publicAdministration the public administration the credentials belong to
     *
     * @return View the view
     */
    public function index(Request $request, PublicAdministration $publicAdministration)
    {
        $credentialsDatatable = [
            'columns' => [
                ['data' => 'client_name', 'name' => __('Nome della credenziale'), 'className' => 'text-wrap'],
                ['data' => 'type', 'name' => __('Tipologia credenziale')],
                ['data' => 'added_at', 'name' => __('aggiunta il')],
                ['data' => 'icons', 'name' => '', 'orderable' => false],
                ['data' => 'buttons', 'name' => '', 'orderable' => false],
            ],
            'source' => $this->getRoleAwareUrl('api-credentials.data.json', [], $publicAdministration),
            'caption' => __('elenco delle credenziali presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['added_at', 'asc'], ['client_name', 'asc']],
        ];

        $roleAwareUrls = $this->getRoleAwareUrlArray([
            'newCredentialUrl' => 'api-credentials.create',
        ], [], $publicAdministration);

        return view('pages.credentials.index')
            ->with($roleAwareUrls)
            ->with($credentialsDatatable);
    }

    /**
     * Show the form for creating a new credential.
     *
     * @param Request $request the request
     * @param PublicAdministration $publicAdministration the public administration the new credential will belong to
     *
     * @return View the view
     */
    public function create(Request $request, PublicAdministration $publicAdministration): View
    {
        $user = $request->user();
        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $credentialsStoreUrl = $this->getRoleAwareUrl('api-credentials.store', [], $currentPublicAdministration);

        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl(
            'api-credentials.websites.permissions',
            ['oldCredentialPermissions' => old('permissions')],
            $currentPublicAdministration
        );

        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource);

        return view('pages.credentials.add')->with(compact('credentialsStoreUrl'))->with($websitesPermissionsDatatable);
    }

    /**
     * Store the credential.
     *
     * @param StoreCredentialsRequest $request the request
     * @param PublicAdministration $publicAdministration the public administration the new credential will belong to
     *
     * @return View|Redirect the view or redirect error
     */
    public function store(StoreCredentialsRequest $request, PublicAdministration $publicAdministration)
    {
        $validatedData = $request->validated();
        $permissions = [];

        if (array_key_exists('permissions', $validatedData)) {
            foreach ($validatedData['permissions'] as $credential => $permission) {
                array_push($permissions, ['id' => $credential, 'permissions' => implode('', $permission)]);
            }
        }

        $user = $request->user();

        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $clientJSON = $this->clientService
            ->makeConsumer(
                $validatedData['credential_name'],
                json_encode(['name' => $validatedData['credential_name'], 'type' => $validatedData['type'], 'siteId' => $permissions])
            );

        $oauthCredentials = $this->clientService->getClient($clientJSON['consumer']['id']);

        $client = Credential::create([
            'client_name' => $validatedData['credential_name'],
            'public_administration_id' => $currentPublicAdministration->id,
            'consumer_id' => $clientJSON['consumer']['id'],
        ]);

        return redirect()->route('api-credentials.index')
            ->withModal($this->getModalCredentialStored($oauthCredentials['client_id'], $oauthCredentials['client_secret']));
    }

    /**
     * Show the credential details page.
     *
     * @param Request $request the request
     * @param Credential $credential the credential
     * @param PublicAdministration $publicAdministration the public administration the credential belongs to
     *
     * @return View|Redirect the view or redirect error
     */
    public function show(Request $request, Credential $credential, PublicAdministration $publicAdministration)
    {
        $user = $request->user();
        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $roleAwareUrls = $this->getRoleAwareUrlArray([
            'credentialEditUrl' => 'api-credentials.edit',
            'credentialRegenerate' => 'api-credentials.regenerate',
        ], [
            'credential' => $credential,
        ], $currentPublicAdministration);

        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl(
            'api-credentials.websites.permissions',
            ['credential' => $credential],
            $currentPublicAdministration
        );

        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource, true);

        $credentialData = [
            'type' => $credential->type,
        ];

        return view('pages.credentials.show')
            ->with(compact('credential'))
            ->with($credentialData)
            ->with($websitesPermissionsDatatable)
            ->with($roleAwareUrls);
    }

    /**
     * Show the form to edit an existing credential.
     *
     * @param Request $request The request
     * @param Credential $credential The credential
     * @param PublicAdministration $publicAdministration the public administration the credential belongs to
     *
     * @return View the view
     */
    public function edit(Request $request, Credential $credential, PublicAdministration $publicAdministration): View
    {
        $user = $request->user();
        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $updateUrl = $this->getRoleAwareUrl('api-credentials.update', [
            'credential' => $credential,
        ], $currentPublicAdministration);

        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl(
            'api-credentials.websites.permissions',
            [
                'credential' => $credential,
                'oldCredentialPermissions' => old('permissions'),
            ],
            $currentPublicAdministration
        );

        $credentialData = [
            'type' => $credential->type,
        ];

        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource);

        return view('pages.credentials.edit')
            ->with(compact('credential', 'updateUrl'))
            ->with($websitesPermissionsDatatable)
            ->with($credentialData);
    }

    /**
     * Update the provided credential.
     *
     * @param UpdateCredentialRequest $request The request
     * @param Credential $credential The credential
     *
     * @return RedirectResponse
     */
    public function update(UpdateCredentialRequest $request, Credential $credential): RedirectResponse
    {
        $validatedData = $request->validated();

        $permissions = [];
        if (array_key_exists('permissions', $validatedData)) {
            foreach ($validatedData['permissions'] as $credentialId => $permission) {
                array_push($permissions, ['id' => $credentialId, 'permissions' => implode('', $permission)]);
            }
        }

        $credential->client_name = $validatedData['credential_name'];
        $credential->save();

        $this->clientService->updateClient($credential->consumer_id, [
            'username' => $validatedData['credential_name'],
            'custom_id' => json_encode(['type' => $validatedData['type'], 'siteId' => $permissions]),
        ]);

        return redirect()->route('api-credentials.index')->withModal([
            'title' => __('modifica credenziale'),
            'icon' => 'it-check-circle',
            'message' => __('La modifica della credenziale :credential è andata a buon fine.', [
                'credential' => '<strong>' . $validatedData['credential_name'] . '</strong>',
            ]),
        ]);
    }

    /**
     * Deletes the credential.
     *
     * @param Request $request The request
     * @param Credential $credential The credential
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function delete(Credential $credential)
    {
        try {
            $this->clientService->deleteConsumer($credential->consumer_id);
            $credential->delete();

            return $this->credentialResponse($credential);
        } catch (InvalidCredentialException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid credential';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Get the Credentials data.
     *
     * @param PublicAdministration $publicAdministration the Public Administration to filter credentials or null to use current one
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataJson(PublicAdministration $publicAdministration)
    {
        $user = auth()->user();
        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $data = $currentPublicAdministration->credentials()->get();

        return DataTables::of($data)
            ->setTransformer(new CredentialsTransformer())
            ->make(true);
    }

    /**
     * Regenerate the credential secret and invalidate all credential's tokens.
     *
     * @param Request $request The Request
     * @param Credential $credential The credential
     *
     * @return RedirectResponse the http redirect response
     */
    public function regenerateCredential(Request $request, Credential $credential): RedirectResponse
    {
        $user = $request->user();
        $currentPublicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $tokens = $this->clientService->getTokensList();

        if ($tokens && array_key_exists('data', $tokens) && is_array($tokens['data'])) {
            $tokens = array_filter($tokens['data'], function ($token) use ($credential) {
                return $token['credential']['id'] === $credential->oauth_client_id;
            });

            foreach ($tokens as $token) {
                $this->clientService->invalidateToken($token['id']);
            }
        }

        $oauthCredentials = $this->clientService->regenerateSecret($credential->client_name, $credential->consumer_id, $credential->client_id);

        return redirect()->route('api-credentials.show', ['credential' => $credential])
            ->withModal($this->getModalCredentialStored($oauthCredentials['client_id'], $oauthCredentials['client_secret'], true));
    }

    /**
     * Show the oauth credential permissions on websites.
     *
     * @param Website $websites websites associated with the credential
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataWebsitesPermissionsJson()
    {
        $websites = current_public_administration()->websites->all();

        return DataTables::of($websites)
            ->setTransformer(new WebsitesPermissionsTransformer())
            ->make(true);
    }

    /**
     * Get the datatable parameters for websites permission with specified source.
     *
     * @param string $source the source paramater for the websites permission datatable
     * @param bool $readonly wether the datatable is readonly
     *
     * @return array the datatable parameters
     */
    public function getDatatableWebsitesPermissionsParams(string $source, bool $readonly = false): array
    {
        return [
            'datatableOptions' => [
                'searching' => [
                    'label' => __('cerca tra i siti web'),
                ],
                'columnFilters' => [
                    'type' => [
                        'filterLabel' => __('tipologia'),
                    ],
                    'status' => [
                        'filterLabel' => __('stato'),
                    ],
                ],
            ],
            'columns' => [
                ['data' => 'website_name', 'name' => __('nome del sito'), 'className' => 'text-wrap'],
                ['data' => 'type', 'name' => __('tipologia')],
                ['data' => 'status', 'name' => __('stato')],
                [
                    'data' => ($readonly ? 'icons' : 'toggles'),
                    'name' => __('permessi della credenziale'),
                    'orderable' => false,
                    'searchable' => false,
                ],
            ],
            'source' => $source . ($readonly ? '?readOnly' : ''),
            'caption' => __('elenco dei siti web presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['website_name', 'asc']],
        ];
    }

    /**
     * Get the datatable parameters for websites permission with specified source.
     *
     * @param string $clientId Credential ID
     * @param string $clientSecret Credential Secret
     * @param bool $regenerated wether the credential is new or regenerated
     *
     * @return array the datatable parameters
     */
    protected function getModalCredentialStored(string $clientId, string $clientSecret, ?bool $regenerated = false): array
    {
        return [
            'title' => $regenerated
                ? __('La credenziale è stata rigenerata')
                : __('La credenziale è stata creata'),
            'icon' => 'it-check-circle',
            'message' => implode(
                "\n",
                [
                    __('Adesso puoi utilizzare la tua nuova credenziale e usare le API con il flusso "Client credentials" OAuth2.') . "\n",
                    '<strong>' . __('Il tuo client_id è:') . '</strong> <span class="text-monospace">' . $clientId . '</span>',
                    '<strong>' . __('Il tuo client_secret è:') . '</strong> <span class="text-monospace">' . $clientSecret . '</span>',
                ]
            ),
            'afterMessage' => implode("\n", [
                '<div class="alert alert-warning mt-5" role="alert">',
                '<h4 class="alert-heading">' . __('Attenzione') . '</h4>',
                __('Prendi nota del tuo :client_secret e conservalo in un luogo sicuro.', [
                    'client_secret' => '<strong>client_secret</strong>',
                ]),
                '<strong>' . __('Non portà essere più visualizzato dopo la chiusura di questo messaggio.') . '</strong>',
                '<hr>',
                '<p class="mb-0">' . __('In caso di smarrimento o compromissione, può essere rigenerato nella pagina di dettaglio della credenziale.') . '</p>' .
                    '</div>',
            ]),
        ];
    }
}
