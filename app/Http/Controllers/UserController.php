<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\User\UserDeleted;
use App\Events\User\UserEmailForPublicAdministrationChanged;
use App\Events\User\UserInvited;
use App\Events\User\UserReactivated;
use App\Events\User\UserSuspended;
use App\Events\User\UserUpdated;
use App\Exceptions\CommandErrorException;
use App\Exceptions\InvalidUserStatusException;
use App\Exceptions\OperationNotAllowedException;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Traits\HasRoleAwareUrls;
use App\Traits\SendsResponse;
use App\Transformers\UserArrayTransformer;
use App\Transformers\UserTransformer;
use App\Transformers\WebsitesPermissionsTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Yajra\DataTables\DataTables;

/**
 * User management controller.
 */
class UserController extends Controller
{
    use SendsResponse;
    use HasRoleAwareUrls;

    /**
     * Display the users list.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the users belong to
     *
     * @return View|RedirectResponse
     */
    public function index(Request $request, PublicAdministration $publicAdministration)
    {
        $authUser = $request->user();
        if ($authUser->publicAdministrations->isEmpty() && $authUser->cannot(UserPermission::ACCESS_ADMIN_AREA)) {
            $request->session()->reflash();

            return redirect()->route('websites.index');
        }

        $usersDatatable = [
            'datatableOptions' => [
                'searching' => [
                    'label' => __('cerca tra gli utenti'),
                ],
                'columnFilters' => [
                    'status' => [
                        'filterLabel' => __('stato'),
                    ],
                ],
            ],
            'columns' => [
                ['data' => 'name', 'name' => __('nome e cognome')],
                ['data' => 'email', 'name' => __('email')],
                ['data' => 'added_at', 'name' => __('iscritto dal')],
                ['data' => 'status', 'name' => __('stato')],
                ['data' => 'icons', 'name' => '', 'orderable' => false],
                ['data' => 'buttons', 'name' => '', 'orderable' => false],
            ],
            'source' => $this->getRoleAwareUrl('users.data.json', [], $publicAdministration),
            'caption' => __('elenco degli utenti presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['added_at', 'asc'], ['name', 'asc']],
        ];

        $userCreateUrl = $this->getRoleAwareUrl('users.create', [], $publicAdministration);

        return view('pages.users.index')->with(compact('userCreateUrl'))->with($usersDatatable);
    }

    /**
     * Provide the users list.
     *
     * @param Request $request the incoming request
     *
     * @return JsonResponse the json response
     */
    public function indexApi(Request $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;
        $UserArrayTransformer = new UserArrayTransformer();
        $users = $publicAdministration->users->map(function ($user) use ($publicAdministration, $UserArrayTransformer) {
            return $UserArrayTransformer->transform($user, $publicAdministration);
        });

        return response()->json($users, 200);
    }

    /**
     * Show the form for creating a new user.
     *
     * @param PublicAdministration $publicAdministration the public administration the new user will belong to
     *
     * @return View the view
     */
    public function create(PublicAdministration $publicAdministration): View
    {
        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl('users.websites.permissions.data.json', [
            'user' => null,
            'oldPermissions' => old('permissions'),
        ], $publicAdministration);
        $userStoreUrl = $this->getRoleAwareUrl('users.store', [], $publicAdministration);
        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource);

        return view('pages.users.add')->with(compact('userStoreUrl'))->with($websitesPermissionsDatatable);
    }

    /**
     * Create a new user.
     *
     * @param StoreUserRequest $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user will belong to
     *
     * @return RedirectResponse the redirect response
     */
    public function store(StoreUserRequest $request, PublicAdministration $publicAdministration): RedirectResponse
    {
        $data = $this->storeMethod($request, $publicAdministration);
        $redirectUrl = $data['redirectUrl'];

        return redirect()->to($redirectUrl)->withModal([
            'title' => $data['title'],
            'icon' => $data['error'] ? 'it-close' : 'it-clock',
            'message' => $data['message'],
            'image' => $data['error'] ? asset('images/closed.svg') : asset('images/invitation-email-sent.svg'),
        ]);
    }

    /**
     * Create a new user.
     *
     * @param StoreUserRequest $request the incoming request
     *
     * @return JsonResponse the json response
     */
    public function storeApi(StoreUserRequest $request): JsonResponse
    {
        $data = $this->storeMethod($request, $request->publicAdministrationFromToken);

        if ($data['error']) {
            return $this->errorResponse($data['error_description'], $this->getErrorCode(User::class), 400);
        }

        return $this->userResponse($data['user'], $request->publicAdministrationFromToken, null, null, 201, [
            'Location' => $this->getUserApiUri($data['user']->fiscal_number),
        ]);
    }

    /**
     * Show the user details page.
     *
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to display
     *
     * @return View the view
     */
    public function show(PublicAdministration $publicAdministration, User $user): View
    {
        $publicAdministration = request()->route('publicAdministration', current_public_administration());
        $emailPublicAdministrationUser = $user->getEmailforPublicAdministration($publicAdministration);
        $statusPublicAdministrationUser = $user->getStatusforPublicAdministration($publicAdministration);

        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl('users.websites.permissions.data.json', [
            'user' => $user,
        ], $publicAdministration);

        $roleAwareUrls = $this->getRoleAwareUrlArray([
            'userEditUrl' => 'users.edit',
            'userVerificationResendUrl' => 'users.verification.resend',
            'userSuspendUrl' => 'users.suspend',
            'userReactivateUrl' => 'users.reactivate',
        ], [
            'user' => $user,
        ], $publicAdministration);
        $allRoles = $this->getAllRoles($user, $publicAdministration);

        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource, true);

        return view('pages.users.show')
            ->with(compact('user', 'allRoles', 'emailPublicAdministrationUser', 'statusPublicAdministrationUser'))
            ->with($roleAwareUrls)
            ->with($websitesPermissionsDatatable);
    }

    /**
     * Retrieve the user details.
     *
     * @param Request $request the incoming request
     *
     * @return JsonResponse the response
     */
    public function showApi(Request $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;
        $user = $request->userFromFiscalNumber;

        return $this->userResponse($user, $publicAdministration);
    }

    /**
     * Show the form to edit an existing user.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to edit
     *
     * @return View the view
     */
    public function edit(Request $request, PublicAdministration $publicAdministration, User $user): View
    {
        $publicAdministration = request()->route('publicAdministration', current_public_administration());
        $emailPublicAdministrationUser = $user->getEmailforPublicAdministration($publicAdministration);

        $oldPermissions = old('permissions', $request->session()->hasOldInput() ? [] : null);
        $websitesPermissionsDatatableSource = $this->getRoleAwareUrl('users.websites.permissions.data.json', [
            'user' => $user,
            'oldPermissions' => $oldPermissions,
        ], $publicAdministration);
        $userUpdateUrl = $this->getRoleAwareUrl('users.update', [
            'user' => $user,
        ], $publicAdministration);

        $websitesPermissionsDatatable = $this->getDatatableWebsitesPermissionsParams($websitesPermissionsDatatableSource);

        if (auth()->user()->can(UserPermission::ACCESS_ADMIN_AREA)) {
            $isAdmin = Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
                return $user->isA(UserRole::ADMIN);
            });
        } else {
            $isAdmin = $user->isA(UserRole::ADMIN);
        }

        return view('pages.users.edit')->with(compact('user', 'userUpdateUrl', 'isAdmin', 'emailPublicAdministrationUser'))->with($websitesPermissionsDatatable);
    }

    /**
     * Update the user information.
     *
     * @param UpdateUserRequest $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to update
     *
     * @return RedirectResponse the redirect response
     */
    public function update(UpdateUserRequest $request, PublicAdministration $publicAdministration, User $user): RedirectResponse
    {
        $data = $this->updateMethod($request, $publicAdministration, $user);
        $redirectUrl = $data['redirectUrl'];
        $updatedUser = $data['user'];

        return $this->userResponse($updatedUser, $publicAdministration, [
            'title' => __('modifica utente'),
            'message' => __("La modifica dell'utente è andata a buon fine."),
        ], $redirectUrl);
    }

    /**
     * Update the user information.
     *
     * @param UpdateUserRequest $request the incoming request
     *
     * @return JsonResponse the response
     */
    public function updateApi(UpdateUserRequest $request): JsonResponse
    {
        $publicAdministration = $request->publicAdministrationFromToken;
        $user = $request->userFromFiscalNumber;

        $data = $this->updateMethod($request, $publicAdministration, $user);
        $updatedUser = $data['user'];

        return $this->userResponse($updatedUser, $publicAdministration);
    }

    /**
     * Remove a user.
     * NOTE: Super admin only.
     *
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to delete
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function delete(PublicAdministration $publicAdministration, User $user)
    {
        $userPublicAdministrationStatus = $user->getStatusforPublicAdministration($publicAdministration);

        try {
            if ($userPublicAdministrationStatus->is(UserStatus::PENDING)) {
                throw new OperationNotAllowedException('Pending users cannot be deleted');
            }

            $validator = validator(request()->all())->after([$this, 'validateNotLastActiveAdministrator']);
            if ($validator->fails()) {
                throw new OperationNotAllowedException($validator->errors()->first('is_admin'));
            }

            Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
                $user->disallow($user->abilities->pluck('id'));
                $user->retract($user->roles->pluck('name'));
            });

            $publicAdministration->users()->detach([$user->id]);
        } catch (OperationNotAllowedException $exception) {
            report($exception);

            return $this->errorResponse($exception->getMessage(), $exception->getCode(), 400);
        }

        event(new UserDeleted($user, $publicAdministration));

        return $this->userResponse($user, $publicAdministration);
    }

    /**
     * Suspend an existing user.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to suspend
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function suspend(Request $request, PublicAdministration $publicAdministration, User $user)
    {
        $isApiRequest = $request->is('api/*');
        $authUser = $request->user();

        if (!$isApiRequest) {
            $publicAdministration = $authUser->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
        } else {
            $publicAdministration = $request->publicAdministrationFromToken;
            $user = $request->userFromFiscalNumber;
        }

        $userPublicAdministrationStatus = $user->getStatusforPublicAdministration($publicAdministration);

        if ($userPublicAdministrationStatus->is(UserStatus::SUSPENDED)) {
            return $this->notModifiedResponse([
                'Location' => $this->getUserApiUri($user->fiscal_number),
            ]);
        }

        try {
            if ($user->is($authUser)) {
                throw new OperationNotAllowedException('Cannot suspend the current authenticated user.');
            }

            if ($userPublicAdministrationStatus->is(UserStatus::PENDING)) {
                throw new InvalidUserStatusException('Pending users cannot be suspended.');
            }

            if ($userPublicAdministrationStatus->is(UserStatus::INVITED)) {
                throw new InvalidUserStatusException('Invited users cannot be suspended.');
            }

            //NOTE: super admin are allowed to suspend the last active administrator of a public administration
            if ($isApiRequest || optional($request->user())->cannot(UserPermission::ACCESS_ADMIN_AREA)) {
                $validator = validator($request->all())->after([$this, 'validateNotLastActiveAdministrator']);
                if ($validator->fails()) {
                    throw new OperationNotAllowedException($validator->errors()->first('is_admin'));
                }
            }

            $publicAdministration->users()->updateExistingPivot($user->id, ['user_status' => UserStatus::SUSPENDED]);

            event(new UserSuspended($user, $publicAdministration));
            event(new UserUpdated($user, $publicAdministration));

            return $this->userResponse($user, $publicAdministration, [
                'title' => __('sospensione utente'),
                'message' => __("La sospensione dell'utente è andata a buon fine."),
            ]);
        } catch (InvalidUserStatusException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current user status';
            $httpStatusCode = 400;
        } catch (OperationNotAllowedException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current user';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Reactivate an existing suspended user.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belong to
     * @param User $user the user to reactivate
     *
     * @return JsonResponse|RedirectResponse the response in json or http redirect format
     */
    public function reactivate(Request $request, PublicAdministration $publicAdministration, User $user)
    {
        $isApiRequest = $request->is('api/*');
        $authUser = $request->user();

        if (!$isApiRequest) {
            $publicAdministration = $authUser->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
        } else {
            $publicAdministration = $request->publicAdministrationFromToken;
            $user = $request->userFromFiscalNumber;
        }

        $userPublicAdministrationStatus = $user->getStatusforPublicAdministration($publicAdministration);

        if (!$userPublicAdministrationStatus->is(UserStatus::SUSPENDED)) {
            return $this->notModifiedResponse([
                'Location' => $this->getUserApiUri($user->fiscal_number),
            ]);
        }

        $publicAdministration->users()->updateExistingPivot($user->id, ['user_status' => UserStatus::ACTIVE]);

        event(new UserReactivated($user, $publicAdministration));
        event(new UserUpdated($user, $publicAdministration));

        return $this->userResponse($user, $publicAdministration, [
            'title' => __('riattivazione utente'),
            'message' => __("La riattivazione dell'utente è andata a buon fine."),
        ]);
    }

    /**
     * Get the users data.
     *
     * @param Request $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataJson(Request $request, PublicAdministration $publicAdministration)
    {
        $users = $request->user()->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration->users()->withTrashed()->get()
            : optional(current_public_administration())->users ?? collect([]);

        return DataTables::of($users)
            ->setTransformer(new UserTransformer())
            ->make(true);
    }

    /**
     * Get the user permissions on websites.
     *
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to initialize permissions or null for default
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response in JSON format
     */
    public function dataWebsitesPermissionsJson(PublicAdministration $publicAdministration, User $user)
    {
        return DataTables::of(auth()->user()->can(UserPermission::ACCESS_ADMIN_AREA)
            ? $publicAdministration->websites
            : current_public_administration()->websites)
            ->setTransformer(new WebsitesPermissionsTransformer())
            ->make(true);
    }

    /**
     * Validate user isn't the last active admin.
     *
     * @param Validator $validator the validator
     */
    public function validateNotLastActiveAdministrator(Validator $validator): void
    {
        $currentRequest = request();

        if (!$currentRequest->is('api/*')) {
            $publicAdministration = $currentRequest->user()->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $currentRequest->route('publicAdministration')
                : current_public_administration();
            $user = $currentRequest->route('user');
        } else {
            $publicAdministration = $currentRequest->publicAdministrationFromToken;
            $user = User::findNotSuperAdminByFiscalNumber($currentRequest->fn);
        }

        if ($user->isTheLastActiveAdministratorOf($publicAdministration)) {
            $validator->errors()->add('is_admin', 'The last administrator cannot be removed or suspended');
        }
    }

    /**
     * Return all roles in for the specified user.
     *
     * @param User $user the user
     * @param PublicAdministration $publicAdministration the public administration scope
     *
     * @return Collection the collection of all the user roles
     */
    protected function getAllRoles(User $user, PublicAdministration $publicAdministration): Collection
    {
        $scope = $publicAdministration->id ?? Bouncer::scope()->get();

        return Bouncer::scope()->onceTo($scope, function () use ($user) {
            return $user->getAllRoles();
        });
    }

    /**
     * Get the datatable parameters for websites permission with specified source.
     *
     * @param string $source the source paramater for the websites permission datatable
     * @param bool $readonly wether the datatable is readonly
     *
     * @return array the datatable parameters
     */
    protected function getDatatableWebsitesPermissionsParams(string $source, bool $readonly = false): array
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
                ['data' => ($readonly ? 'icons' : 'toggles'), 'name' => __('permessi sui dati analytics'), 'orderable' => false, 'searchable' => false],
            ],
            'source' => $source . ($readonly ? '?readOnly' : ''),
            'caption' => __('elenco dei siti web presenti su :app', ['app' => config('app.name')]),
            'columnsOrder' => [['website_name', 'asc']],
        ];
    }

    /**
     * Create a new user.
     *
     * @param StoreUserRequest $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user will belong to
     *
     * @return array the data to be used in the response
     */
    protected function storeMethod(StoreUserRequest $request, PublicAdministration $publicAdministration): array
    {
        $authUser = $request->user();
        $validatedData = $request->validated();

        if (!$request->is('api/*')) {
            $publicAdministration = $authUser->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
            $redirectUrl = $this->getRoleAwareUrl('users.index', [], $publicAdministration);
        }

        // If existingUser is filled the user is already in the database
        if (isset($validatedData['existingUser']) && isset($validatedData['existingUser']->email)) {
            $user = $validatedData['existingUser'];
            $userInCurrentPublicAdministration = $user->publicAdministrationsWithSuspended->where('id', $publicAdministration->id)->isNotEmpty();

            if ($userInCurrentPublicAdministration) {
                return [
                    'error' => true,
                    'error_description' => 'User already exists in the current public administration',
                    'title' => __("Non è possibile inoltrare l'invito"),
                    'message' => __("L'utente fa già parte di questa pubblica amministrazione."),
                    'redirectUri' => $redirectUrl ?? null,
                ];
            }

            $userMessage = implode("\n", [
                __("Abbiamo inviato un invito all'indirizzo email :email.", ['email' => '<strong>' . e($validatedData['email']) . '</strong>']) . "\n",
                __("L'invito potrà essere confermato dall'utente al prossimo accesso."),
            ]);
        } else {
            $user = User::create([
                'uuid' => Uuid::uuid4()->toString(),
                'fiscal_number' => $validatedData['fiscal_number'],
                'email' => $validatedData['email'],
                'status' => UserStatus::INVITED,
            ]);

            $userMessage = implode("\n", [
                __("Abbiamo inviato un invito all'indirizzo email :email.", ['email' => '<strong>' . e($validatedData['email']) . '</strong>']) . "\n",
                __("L'invito scade dopo :expire giorni e può essere rinnovato.", ['expire' => config('auth.verification.expire')]) . "\n",
                '<strong>' . __("Attenzione! Se dopo :purge giorni l'utente non avrà ancora accettato l'invito, sarà rimosso.", ['purge' => config('auth.verification.purge')]) . '</strong>',
            ]);
        }

        if (!$user->hasAnalyticsServiceAccount()) {
            $user->registerAnalyticsServiceAccount();
        }

        $user->publicAdministrations()->attach(
            $publicAdministration->id,
            ['user_email' => $validatedData['email'], 'user_status' => UserStatus::INVITED]
        );

        $this->manageUserPermissions($validatedData, $publicAdministration, $user);

        event(new UserInvited($user, $authUser, $publicAdministration));

        return [
            'error' => false,
            'title' => 'Invito inoltrato',
            'message' => $userMessage,
            'redirectUrl' => $redirectUrl ?? null,
            'user' => $user,
        ];
    }

    /**
     * Update an existing user.
     *
     * @param UpdateUserRequest $request the incoming request
     * @param PublicAdministration $publicAdministration the public administration the user belongs to
     * @param User $user the user to update
     *
     * @return array the data to be used in the response
     */
    protected function updateMethod(UpdateUserRequest $request, PublicAdministration $publicAdministration, User $user): array
    {
        $authUser = $request->user();
        $validatedData = $request->validated();

        if (!$request->is('api/*')) {
            $publicAdministration = $authUser->can(UserPermission::ACCESS_ADMIN_AREA)
                ? $publicAdministration
                : current_public_administration();
            $redirectUrl = $this->getRoleAwareUrl('users.index', [], $publicAdministration);
        }

        $emailPublicAdministrationUser = $user->getEmailforPublicAdministration($publicAdministration);

        if ($user->status->is(UserStatus::INVITED) && array_key_exists('fiscal_number', $validatedData)) {
            $user->fiscal_number = $validatedData['fiscal_number'];
        }

        if ($user->isDirty()) {
            $user->save();
        }

        $this->manageUserPermissions($validatedData, $publicAdministration, $user);

        if ($emailPublicAdministrationUser !== $validatedData['emailPublicAdministrationUser']) {
            $user->publicAdministrations()->updateExistingPivot($publicAdministration->id, ['user_email' => $validatedData['emailPublicAdministrationUser']]);

            event(new UserEmailForPublicAdministrationChanged($user, $publicAdministration, $validatedData['emailPublicAdministrationUser']));
        }

        return [
            'redirectUrl' => $redirectUrl ?? null,
            'user' => $user,
        ];
    }

    /**
     * Manage websites permissions for a user.
     *
     * @param array $validatedData the permissions array
     * @param PublicAdministration $publicAdministration the public administration the website belongs to
     * @param User $user the user
     *
     * @throws BindingResolutionException if unable to bind to the service
     * @throws AnalyticsServiceException if unable to contact the Analytics Service
     * @throws CommandErrorException if command finishes with error
     * @throws TenantIdNotSetException if the tenant id is not set in the current session
     */
    protected function manageUserPermissions(array $validatedData, PublicAdministration $publicAdministration, User $user): void
    {
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($validatedData, $publicAdministration, $user) {
            $isAdmin = $validatedData['is_admin'] ?? false;
            $websitesPermissions = $validatedData['permissions'] ?? [];
            $publicAdministration->websites->map(function ($website) use ($user, $isAdmin, $websitesPermissions) {
                if (request()->is('api/*')) {
                    $websiteKey = $website->slug;
                } else {
                    $websiteKey = $website->id;
                }

                if ($isAdmin) {
                    $user->retract(UserRole::DELEGATED);
                    $user->assign(UserRole::ADMIN);
                    $user->setWriteAccessForWebsite($website);

                    return $user;
                } else {
                    $user->retract(UserRole::ADMIN);
                    $user->assign(UserRole::DELEGATED);
                    if (empty($websitesPermissions[$websiteKey])) {
                        $user->setNoAccessForWebsite($website);

                        return $user;
                    }

                    if (in_array(UserPermission::MANAGE_ANALYTICS, $websitesPermissions[$websiteKey])) {
                        $user->setWriteAccessForWebsite($website);

                        return $user;
                    }

                    if (in_array(UserPermission::READ_ANALYTICS, $websitesPermissions[$websiteKey])) {
                        $user->setViewAccessForWebsite($website);

                        return $user;
                    }
                }
            })->map(function ($user) use ($publicAdministration) {
                if ($user->hasAnalyticsServiceAccount()) {
                    $user->syncWebsitesPermissionsToAnalyticsService($publicAdministration);
                }
            });
        });
    }

    /**
     * Get the URI to retrieve the specified user via API.
     *
     * @param string $fn fiscal number of the user
     *
     * @return string the URI to retrieve the specified user
     */
    protected function getUserApiUri(string $fn): string
    {
        return config('kong-service.api_url') .
            str_replace('/api/', '/portal/', route('api.users.show', ['fn' => $fn], false));
    }
}
