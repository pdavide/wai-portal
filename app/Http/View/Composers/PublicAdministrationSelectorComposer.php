<?php

namespace App\Http\View\Composers;

use App\Enums\UserRole;
use App\Models\PublicAdministration;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\View\View;

class PublicAdministrationSelectorComposer
{
    /**
     * The current request.
     *
     * @var Request
     */
    protected $request;

    /**
     * The current session.
     *
     * @var Store
     */
    protected $session;

    /**
     * Create a new PublicAdministrationSelectorComposer.
     *
     * @param Request $request
     * @param Store $session
     */
    public function __construct(Request $request, Store $session)
    {
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * Bind publicAdministrationSelectorArray data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $lastRoute = $this->request->route();
        $lastRouteName = $lastRoute->getName();
        $lastRouteParameters = $lastRoute->parameters();
        $authUser = $this->request->user();
        $targetRouteHasPublicAdministrationParam = array_key_exists('publicAdministration', $lastRouteParameters);
        $selectTenantUrl = route('publicAdministrations.select');

        if ($lastRoute->isFallback) {
            return;
        }

        switch (true) {
            case 'admin.dashboard' === $lastRouteName:
            case 'home' === $lastRouteName:
                $targetRoute = 'analytics';
                $targetRouteHasPublicAdministrationParam = true;

                break;
            case array_key_exists('website', $lastRouteParameters):
                $targetRoute = 'websites.index';

                break;
            case array_key_exists('user', $lastRouteParameters):
                $targetRoute = 'users.index';

                break;
            default:
                $targetRoute = $lastRouteName;
        }

        if ($authUser && $authUser->isA(UserRole::SUPER_ADMIN)) {
            if ($targetRouteHasPublicAdministrationParam && 'admin.' !== substr($targetRoute, 0, 6)) {
                $targetRoute = 'admin.publicAdministration.' . $targetRoute;
            }

            $publicAdministrationSelectorArray = PublicAdministration::all([
                'ipa_code',
                'name',
            ])->sortBy('name')->toArray();

            $publicAdministrationShowSelector = true;
            $selectTenantUrl = route('admin.publicAdministrations.select');
            $hasPublicAdministration = count($publicAdministrationSelectorArray) >= 1;
        } elseif ($authUser) {
            $publicAdministrationSelectorArray = $authUser->publicAdministrations()->get()
                ->map(function ($publicAdministration) {
                    return collect($publicAdministration->toArray())
                        ->only(['id', 'ipa_code', 'name', 'url'])
                        ->all();
                })->sortBy('name')->values()->toArray();

            $publicAdministrationShowSelector = count($publicAdministrationSelectorArray) > 1;
            $hasPublicAdministration = count($publicAdministrationSelectorArray) >= 1;
        } else {
            $publicAdministrationSelectorArray = [];
            $publicAdministrationShowSelector = false;
            $hasPublicAdministration = false;
        }

        $view->with('publicAdministrationSelectorArray', $publicAdministrationSelectorArray);
        $view->with('publicAdministrationShowSelector', $publicAdministrationShowSelector);
        $view->with('selectTenantUrl', $selectTenantUrl);
        $view->with('targetRoute', $targetRoute);
        $view->with('targetRouteHasPublicAdministrationParam', $targetRouteHasPublicAdministrationParam);
        $view->with('hasPublicAdministration', $hasPublicAdministration);
    }
}
