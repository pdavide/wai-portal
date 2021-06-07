<?php

namespace App\Http\Controllers;

use App\Enums\PublicAdministrationStatus;
use App\Enums\UserPermission;
use App\Enums\UserStatus;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteUnarchived;
use App\Exceptions\AnalyticsServiceException;
use App\Exceptions\CommandErrorException;
use App\Exceptions\InvalidWebsiteStatusException;
use App\Exceptions\OperationNotAllowedException;
use App\Exceptions\TenantIdNotSetException;
use App\Http\Requests\StorePrimaryWebsiteRequest;
use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Requests\UpdateWebsiteRequest;
use App\Models\PublicAdministration;
use App\Models\Website;
use App\Traits\ActivatesWebsite;
use App\Traits\HasRoleAwareUrls;
use App\Traits\ManagePublicAdministrationRegistration;
use App\Traits\SendsResponse;
use App\Transformers\UsersPermissionsTransformer;
use App\Transformers\WebsiteArrayTransformer;
use App\Transformers\WebsiteTransformer;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\DataTables;

/**
 * Website management controller.
 */
class WebsiteController extends Controller
{
    use ActivatesWebsite;
    use SendsResponse;
    use HasRoleAwareUrls;
    use ManagePublicAdministrationRegistration;

    /**
     * Display the websites list.
     *
     * @param PublicAdministration $publicAdministration the public administration the websites belong to
     *
     * @return View the view
     */
    public function index(PublicAdministration $publicAdministration): View
    {
        $websitesDatatable = [
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
                ['data' => 'added_at', 'name' => __('aggiunto il')],
                ['data' => 'status', 'name' => __('stato')],
                ['data' => 'icons', 'name' => '', 'orderable' => false],
                ['data' => 'buttons', 'name' => '', 'orderable' => false],
            ],
            'source' => $this->getRoleAwareUrl('websites.data.json', [], $publicAdministration),
            'caption' => __('elenco dei siti web presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['added_at', 'asc'], ['website_name', 'asc']],
        ];

        $websiteCreateUrl = $this->getRoleAwareUrl('websites.create', [], $publicAdministration);

        return view('pages.websites.index')->with(compact('websiteCreateUrl'))->with($websitesDatatable);
    }

    /**
     * Show the form for creating a new custom website.
     *
     * @return View the view
     */
    public function custom()
    {
        return view('pages.pa.add')->with('customForm', true);
    }

    /**
     * Create a new primary website.
     *
     * @param StorePrimaryWebsiteRequest $request the request
     *
     * @return RedirectResponse the server redirect response
     */
    public function storePrimary(StorePrimaryWebsiteRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        $publicAdministration = PublicAdministration::make([
            'ipa_code' => $request->isCustomPublicAdministration ? Str::slug($request->publicAdministration['url']) : $request->publicAdministration['ipa_code'],
            'name' => $request->publicAdministration['name'],
            'pec' => $request->publicAdministration['pec'] ?? null,
            'rtd_name' => $request->publicAdministration['rtd_name'] ?? null,
            'rtd_mail' => $request->publicAdministration['rtd_mail'] ?? null,
            'rtd_pec' => $request->publicAdministration['rtd_pec'] ?? null,
            'city' => $request->publicAdministration['city'] ?? null,
            'county' => $request->publicAdministration['county'] ?? null,
            'region' => $request->publicAdministration['region'] ?? null,
            'type' => $request->publicAdministration['type'],
            'status' => PublicAdministrationStatus::PENDING,
        ]);

        $publicAdministration->save();

        $siteUrl = $request->isCustomPublicAdministration ? $request->publicAdministration['url'] : $request->publicAdministration['site'];
        $website = $this->registerPublicAdministration($authUser, $publicAdministration, $siteUrl, $request->isCustomPublicAdministration, $request->input('email'));

        event(new WebsiteAdded($website, $authUser));

        return redirect()->route('websites.index')->withModal([
            'title' => __('Il sito è stato inserito, adesso procedi ad attivarlo!'),
            'icon' => 'it-check-circle',
            'message' => __('Abbiamo inviato al tuo indirizzo email le istruzioni per attivare il sito e iniziare a monitorare il traffico.'),
            'image' => asset('images/primary-website-added.svg'),
        ]);
    }

    /**
     * Show the form for creating a new website.
     *
     * @param PublicAdministration $publicAdministration the public administration the new website will belong to
     *
     * @return View the view
     */
    public function create(PublicAdministration $publicAdministration): View
    {
        $usersPermissionsDatatableSource = $this->getRoleAwareUrl('websites.users.permissions.data.json', [
            'website' => null,
            'oldPermissions' => old('permissions'),
        ], $publicAdministration);
        $websiteStoreUrl = $this->getRoleAwareUrl('websites.store', [], $publicAdministration);
        $usersPermissionsDatatable = $this->getDatatableUsersPermissionsParams($usersPermissionsDatatableSource);

        return view('pages.websites.add')->with(compact('websiteStoreUrl'))->with($usersPermissionsDatatable);
    }

    /**
     * Create a new website (portal method).
     *
     * @param StoreWebsiteRequest $request the request
     * @param PublicAdministration $publicAdministration the public administration
     *
     * @return RedirectResponse the server redirect response
     */
    public function store(StoreWebsiteRequest $request, PublicAdministration $publicAdministration): RedirectResponse
    {
        $this->storeMethod($request, $publicAdministration);

        $redirectUrl = $this->getRoleAwareUrl('websites.index', [], $publicAdministration);

        return redirect()->to($redirectUrl)->withModal([
            'title' => __('Il sito è stato inserito, adesso procedi ad attivarlo!'),
            'icon' => 'it-check-circle',
            'message' => __('Abbiamo inviato al tuo indirizzo email le istruzioni per attivare il sito e iniziare a monitorare il traffico.'),
            'image' => asset('images/website-added.svg'),
        ]);
    }

    /**
     * Create a new website (API method).
     *
     * @param StoreWebsiteRequest $request the request
     *
     * @return JsonResponse the server JSON response
     */
    public function storeApi(StoreWebsiteRequest $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;

        $data = $this->storeMethod($request, $publicAdministration);

        if (is_array($data) && array_key_exists('website', $data)) {
            return $this->websiteResponse($data['website'], null, null, 201, [
                'Location' => $this->getWebsiteAPIUri($data['website']),
            ]);
        }

        return $this->errorResponse('Cannot create the website', $this->getErrorCode(Website::class), 500);
    }

    /**
     * Show the website details page.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to show
     *
     * @return JsonResponse|View the view
     */
    public function show(Request $request, PublicAdministration $publicAdministration, Website $website)
    {
        $isApiRequest = $request->is('api/*');
        if ($isApiRequest) {
            return $this->websiteResponse($website);
        }

        $currentPublicAdministration = auth()->user()->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration
            : current_public_administration();

        $usersPermissionsDatatableSourceUrl = $this->getRoleAwareUrl('websites.users.permissions.data.json', [
            'website' => $website,
        ], $currentPublicAdministration);
        $roleAwareUrls = $this->getRoleAwareUrlArray([
            'websiteEditUrl' => 'websites.edit',
            'websiteTrackingCheckUrl' => 'websites.tracking.check',
            'websiteTrackingForceUrl' => 'websites.activate.force',
            'websiteArchiveUrl' => 'websites.archive',
            'websiteUnarchiveUrl' => 'websites.unarchive',
            'javascriptSnippetUrl' => 'websites.snippet.javascript',
        ], [
            'website' => $website,
        ], $currentPublicAdministration);

        $usersPermissionsDatatable = $this->getDatatableUsersPermissionsParams($usersPermissionsDatatableSourceUrl, true);

        $authUser = auth()->user();
        $userPublicAdministrationStatus = $authUser->getStatusforPublicAdministration($currentPublicAdministration);

        $forceActivationButtonVisible = !app()->environment('production') && config('wai.custom_public_administrations', false) && $website->type->is(WebsiteType::INSTITUTIONAL_PLAY);

        return view('pages.websites.show')->with(compact('website'))->with($roleAwareUrls)
            ->with($usersPermissionsDatatable)
            ->with('forceActivationButtonVisible', $forceActivationButtonVisible)
            ->with('userPublicAdministrationStatus', $userPublicAdministrationStatus);
    }

    /**
     * Show the form for editing a website.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to edit
     *
     * @return View the view
     */
    public function edit(Request $request, PublicAdministration $publicAdministration, Website $website): View
    {
        $oldPermissions = old('permissions', $request->session()->hasOldInput() ? [] : null);
        $usersPermissionsDatatableSourceUrl = $this->getRoleAwareUrl('websites.users.permissions.data.json', [
            'website' => $website,
            'oldPermissions' => $oldPermissions,
        ], $publicAdministration);
        $updateUrl = $this->getRoleAwareUrl('websites.update', [
            'website' => $website,
        ], $publicAdministration);
        $usersPermissionsDatatable = $this->getDatatableUsersPermissionsParams($usersPermissionsDatatableSourceUrl);

        return view('pages.websites.edit')->with(compact('website', 'updateUrl'))->with($usersPermissionsDatatable);
    }

    /**
     * Update a website.
     * NOTE: for primary websites, only updates to user permissions are allowed.
     *
     * @param UpdateWebsiteRequest $request the request
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to update
     *
     * @return JsonResponse|RedirectResponse the server redirect response
     */
    public function update(UpdateWebsiteRequest $request, PublicAdministration $publicAdministration, Website $website)
    {
        $isApiRequest = $request->is('api/*');
        if ($isApiRequest) {
            $publicAdministration = $request->publicAdministrationFromToken;
        }

        $website = $this->updateMethod($request, $publicAdministration, $website);
        $redirectUrl = null;
        $notification = [];

        if (!$isApiRequest) {
            $redirectUrl = $this->getRoleAwareUrl('websites.index', [], $publicAdministration);
            $notification = [
                'title' => __('modifica sito'),
                'message' => __('La modifica del sito è andata a buon fine.'),
                'status' => 'success',
                'icon' => 'it-check-circle',
            ];
        }

        return $this->websiteResponse($website, $notification, $redirectUrl);
    }

    /**
     * Delete a website.
     * NOTE: super-admin only.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to delete
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function delete(PublicAdministration $publicAdministration, Website $website)
    {
        if ($website->trashed()) {
            return $this->notModifiedResponse([
                'Location' => $this->getWebsiteAPIUri($website),
            ]);
        }

        try {
            if ($website->type->is(WebsiteType::INSTITUTIONAL)) {
                throw new OperationNotAllowedException('Delete request not allowed on primary website ' . $website->info);
            }

            app()->make('analytics-service')->changeArchiveStatus($website->analytics_id, WebsiteStatus::ARCHIVED);
            $website->delete();

            $this->updateWebsiteListCache($website);

            return $this->websiteResponse($website, [
                'title' => __('cancellazione sito web'),
                'message' => __('Il sito web :website è stato eliminato', ['website' => $website->name]),
            ]);
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (OperationNotAllowedException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = $exception->getMessage();
            $httpStatusCode = 400;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Restore a deleted website.
     * NOTE: super-admin only.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to restore
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function restore(PublicAdministration $publicAdministration, Website $website)
    {
        if (!$website->trashed()) {
            return $this->notModifiedResponse([
                'Location' => $this->getWebsiteAPIUri($website),
            ]);
        }

        try {
            // NOTE: WebsiteStatus::ACTIVE is for re-enabling tracking on the Analytics Service: actual website status isn't changed
            app()->make('analytics-service')->changeArchiveStatus($website->analytics_id, WebsiteStatus::ACTIVE);
            $website->restore();

            return $this->websiteResponse($website, [
                'title' => __('ripristino sito web'),
                'message' => __('Il sito web :website è stato ripristinato', ['website' => $website->name]),
            ]);
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Check website tracking status.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to check
     *
     * @return JsonResponse|RedirectResponse the response
     */
    public function checkTracking(PublicAdministration $publicAdministration, Website $website)
    {
        try {
            if ($website->status->is(WebsiteStatus::PENDING)) {
                if ($this->hasActivated($website)) {
                    $this->activate($website);

                    event(new WebsiteActivated($website));

                    return $this->websiteResponse($website, [
                        'title' => __('attivazione sito web'),
                        'message' => __('Il sito web :website è stato attivato', ['website' => $website->name]),
                    ]);
                }

                return $this->notModifiedResponse([
                    'Location' => $this->getWebsiteAPIUri($website),
                ]);
            }

            throw new InvalidWebsiteStatusException('Unable to check activation for website ' . $website->info . ' in status ' . $website->status->key . '.');
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (InvalidWebsiteStatusException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website status';
            $httpStatusCode = 400;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Force website tracking status to active.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to check
     *
     * @return JsonResponse|RedirectResponse the response
     */
    public function forceActivation(PublicAdministration $publicAdministration, Website $website)
    {
        try {
            if ($website->status->is(WebsiteStatus::PENDING) && !app()->environment('production')) {
                $this->activate($website);
                event(new WebsiteActivated($website));

                return $this->websiteResponse($website, [
                    'title' => __('attivazione sito web'),
                    'message' => __('Il sito web :website è stato attivato', ['website' => $website->name]),
                ]);
            }

            throw new InvalidWebsiteStatusException('Unable to force activation for website ' . $website->info . ' in status ' . $website->status->key . '.');
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (InvalidWebsiteStatusException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website status';
            $httpStatusCode = 400;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Archive website request.
     * Only active and not primary type websites can be archived.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website
     *
     * @return JsonResponse|RedirectResponse the response
     */
    public function archive(PublicAdministration $publicAdministration, Website $website)
    {
        if ($website->status->is(WebsiteStatus::ARCHIVED)) {
            return $this->notModifiedResponse([
                'Location' => $this->getWebsiteAPIUri($website),
            ]);
        }

        try {
            if (!$website->type->is(WebsiteType::INSTITUTIONAL)) {
                if ($website->status->is(WebsiteStatus::ACTIVE)) {
                    $website->status = WebsiteStatus::ARCHIVED;
                    app()->make('analytics-service')->changeArchiveStatus($website->analytics_id, WebsiteStatus::ARCHIVED);
                    $website->save();

                    event(new WebsiteArchived($website, true));

                    return $this->websiteResponse($website, [
                        'title' => __('archivio sito web'),
                        'message' => __('Il sito web :website è stato archiviato', ['website' => $website->name]),
                    ]);
                }

                throw new InvalidWebsiteStatusException('Unable to archive website ' . $website->info . ' in status ' . $website->status->key . '.');
            }

            throw new OperationNotAllowedException('Archive request not allowed on primary website ' . $website->info . '.');
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (InvalidWebsiteStatusException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website status';
            $httpStatusCode = 400;
        } catch (OperationNotAllowedException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website';
            $httpStatusCode = 400;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Re-enable an archived website.
     * Only archived and not primary type websites can be re-enabled.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website
     *
     * @return JsonResponse|RedirectResponse the response
     */
    public function unarchive(PublicAdministration $publicAdministration, Website $website)
    {
        try {
            if (!$website->type->is(WebsiteType::INSTITUTIONAL)) {
                if ($website->status->is(WebsiteStatus::ACTIVE)) {
                    return $this->notModifiedResponse([
                        'Location' => $this->getWebsiteAPIUri($website),
                    ]);
                }

                if ($website->status->is(WebsiteStatus::ARCHIVED)) {
                    $website->status = WebsiteStatus::ACTIVE;
                    app()->make('analytics-service')->changeArchiveStatus($website->analytics_id, WebsiteStatus::ACTIVE);
                    $website->save();

                    event(new WebsiteUnarchived($website));

                    return $this->websiteResponse($website, [
                        'title' => __('ripristino sito web'),
                        'message' => __('Il sito web :website è stato ripristinato', ['website' => $website->name]),
                    ]);
                }

                throw new InvalidWebsiteStatusException('Unable to cancel archiving for website ' . $website->info . ' in status ' . $website->status->key . '.');
            }

            throw new OperationNotAllowedException('Cancel archiving request not allowed on primary website ' . $website->info . '.');
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (InvalidWebsiteStatusException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website status';
            $httpStatusCode = 400;
        } catch (OperationNotAllowedException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current website';
            $httpStatusCode = 400;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Get Javascript snippet for a website.
     *
     * @param Website $website the website
     *
     * @throws BindingResolutionException if unable to bind to the service
     * @throws AnalyticsServiceException if unable to contact the Analytics Service
     * @throws CommandErrorException if command finishes with error
     *
     * @return JsonResponse the JSON response
     */
    public function showJavascriptSnippet(Request $request, PublicAdministration $publicAdministration, Website $website): JsonResponse
    {
        try {
            $javascriptSnippet = app()->make('analytics-service')->getJavascriptSnippet($website->analytics_id);
            $jsonResponse = [
                'javascriptSnippet' => trim($javascriptSnippet),
            ];

            if (!$request->is('api/*')) {
                $jsonResponse = array_merge($jsonResponse, [
                    'result' => 'ok',
                    'id' => $website->slug,
                    'name' => e($website->name),
                ]);
            }

            return response()->json($jsonResponse);
        } catch (AnalyticsServiceException | BindingResolutionException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Internal Server Error';
            $httpStatusCode = 500;
        } catch (CommandErrorException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Bad Request';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Get the websites data.
     *
     * @param PublicAdministration $publicAdministration the Public Administration to filter websites or null to use current one
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataJson(PublicAdministration $publicAdministration)
    {
        $data = auth()->user()->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration->websites()->withTrashed()->get()
            : current_public_administration()->websites();

        return DataTables::of($data)
            ->setTransformer(new WebsiteTransformer())
            ->make(true);
    }

    /**
     * Get the websites data.
     *
     * @param Request $request the request
     *
     * @return JsonResponse the response
     */
    public function dataApi(Request $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;
        $websites = $publicAdministration->websites()->get()->map(function ($website) {
            return (new WebsiteArrayTransformer())->transform($website);
        });

        return response()->json($websites, 200);
    }

    /**
     * Get the websites list for the public administration.
     *
     * @param Request $request the request
     *
     * @return JsonResponse the response
     */
    public function websiteList(Request $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;
        $websites = $publicAdministration->websites()->get()->toArray();
        $websites = array_map(function ($elem) {
            return $elem['url'];
        }, $websites);

        return response()->json($websites, 200);
    }

    /**
     * Get the user permissions on a website.
     *
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param Website $website the website to use for permissions initialization
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataUsersPermissionsJson(PublicAdministration $publicAdministration, Website $website)
    {
        $users = auth()->user()->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration->users
            : current_public_administration()->users()->where('status', '!=', UserStatus::SUSPENDED);

        return DataTables::of($users)
            ->setTransformer(new UsersPermissionsTransformer())
            ->make(true);
    }

    /**
     * Get the datatable parameters for users permission with specified source.
     *
     * @param string $source the source paramater for the users permission datatable
     * @param bool $readonly wether the datatable is readonly
     *
     * @return array the datatable parameters
     */
    public function getDatatableUsersPermissionsParams(string $source, bool $readonly = false): array
    {
        return [
            'datatableOptions' => [
                'searching' => [
                    'label' => __('cerca tra gli utenti'),
                ],
            ],
            'columns' => [
                ['data' => 'name', 'name' => __('nome e cognome')],
                ['data' => 'email', 'name' => __('email')],
                ['data' => 'status', 'name' => __('stato')],
                ['data' => ($readonly ? 'icons' : 'toggles'), 'name' => __('permessi sui dati analytics'), 'orderable' => false, 'searchable' => false],
            ],
            'source' => $source . ($readonly ? '?readOnly' : ''),
            'caption' => __('elenco degli utenti presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['name', 'asc']],
        ];
    }

    /**
     * Store a new website.
     *
     * @param StoreWebsiteRequest $request the request
     * @param PublicAdministration $publicAdministration the public administration the website will belong to
     *
     * @return array the new website
     */
    protected function storeMethod(StoreWebsiteRequest $request, PublicAdministration $publicAdministration): array
    {
        $user = $request->user();
        $validatedData = $request->validated();

        if (!$request->is('api/*')) {
            $publicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
        }

        $analyticsId = app()->make('analytics-service')->registerSite($validatedData['website_name'], $validatedData['url'], $publicAdministration->name);

        $website = Website::create([
            'name' => $validatedData['website_name'],
            'url' => $validatedData['url'],
            'type' => (int) $validatedData['type'],
            'public_administration_id' => $publicAdministration->id,
            'analytics_id' => $analyticsId,
            'slug' => Str::slug($validatedData['url']),
            'status' => WebsiteStatus::PENDING,
        ]);

        if (null !== $user) {
            event(new WebsiteAdded($website, $user));
        }

        $publicAdministration->getAdministrators()->map(function ($administrator) use ($website, $publicAdministration) {
            $administrator->setWriteAccessForWebsite($website);
            $administrator->syncWebsitesPermissionsToAnalyticsService($publicAdministration);
        });

        $this->manageWebsitePermissionsOnNonAdministrators($validatedData, $publicAdministration, $website);

        $this->updateWebsiteListCache($website);

        return [
            'website' => $website,
        ];
    }

    /**
     * Get the websites list for the public administration.
     *
     * @param UpdateWebsiteRequest $request the request
     * @param PublicAdministration $publicAdministration the public administration
     * @param Website $website the website
     */
    protected function updateMethod(UpdateWebsiteRequest $request, PublicAdministration $publicAdministration, Website $website)
    {
        $validatedData = $request->validated();
        $user = auth()->user();

        if (!$request->is('api/*')) {
            $publicAdministration = $user->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
        }

        if (!$website->type->is(WebsiteType::INSTITUTIONAL)) {
            if ($website->slug !== Str::slug($validatedData['url'])) {
                app()->make('analytics-service')->updateSite($website->analytics_id, $validatedData['website_name'] . ' [' . $validatedData['type'] . ']', $validatedData['url'], $website->publicAdministration->name);
            }

            $website->fill([
                'name' => $validatedData['website_name'],
                'url' => $validatedData['url'],
                'type' => $validatedData['type'],
                'slug' => Str::slug($validatedData['url']),
            ]);
            $website->save();
        }

        $this->manageWebsitePermissionsOnNonAdministrators($validatedData, $publicAdministration, $website);

        $this->updateWebsiteListCache($website);

        return $website;
    }

    /**
     * Manage non-admin users permissions on a website.
     *
     * @param array $validatedData the permissions array
     * @param PublicAdministration $publicAdministration the public administration the website belogns to
     * @param Website $website the website
     *
     * @throws BindingResolutionException if unable to bind to the service
     * @throws AnalyticsServiceException if unable to contact the Analytics Service
     * @throws CommandErrorException if command finishes with error
     * @throws TenantIdNotSetException if the tenant id is not set in the current session
     */
    private function manageWebsitePermissionsOnNonAdministrators(array $validatedData, PublicAdministration $publicAdministration, Website $website): void
    {
        $usersPermissions = $validatedData['permissions'] ?? [];
        $publicAdministration->getNonAdministrators()->map(function ($user) use ($website, $usersPermissions) {
            if (request()->is('api/*')) {
                $userKey = $user->fiscal_number;
            } else {
                $userKey = $user->id;
            }

            if (empty($usersPermissions[$userKey])) {
                $user->setNoAccessForWebsite($website);

                return $user;
            }

            if (in_array(UserPermission::MANAGE_ANALYTICS, $usersPermissions[$userKey])) {
                $user->setWriteAccessForWebsite($website);

                return $user;
            }

            if (in_array(UserPermission::READ_ANALYTICS, $usersPermissions[$userKey])) {
                $user->setViewAccessForWebsite($website);

                return $user;
            }
        })->map(function ($user) use ($publicAdministration) {
            if ($user->hasAnalyticsServiceAccount()) {
                $user->syncWebsitesPermissionsToAnalyticsService($publicAdministration);
            }
        });
    }

    /**
     * Update the cache for websites list.
     *
     * @param Website $website the website
     *
     * @return void
     */
    private function updateWebsiteListCache(Website $website)
    {
        $id = $website->analytics_id;
        $list = app()->make('analytics-service')->getSiteUrlsFromId($id);

        Cache::put($id, implode(' ', $list));
    }

    /**
     * Get the uri for website api.
     *
     * @param Website $website the website
     *
     * @return string the uri
     */
    private function getWebsiteAPIUri(Website $website): string
    {
        return 'to-be-implemented';
        // return config('kong-service.api_url') .
            // str_replace('/api/', '/portal/', route('api.websites.read', ['website' => $website], false));
    }
}
