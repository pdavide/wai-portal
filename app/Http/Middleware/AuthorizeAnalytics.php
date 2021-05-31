<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Analytics Service authorization middleware.
 */
class AuthorizeAnalytics
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request the request
     * @param \Closure $next the next closure
     * @param string $action the requested analytics action
     *
     * @return mixed the check result
     */
    public function handle($request, Closure $next, string $action)
    {
        $authUser = $request->user();

        if ($authUser->can($action)) {
            $authorized = true;
        }

        $currentPublicAdministration = current_public_administration();

        if ($request->route('website')) {
            if ($authUser->can($action, $request->route('website'))) {
                $authorized = true;
            }

            if ($request->routeIs('websites.tracking.check') || $request->routeIs('websites.activate.force') || $request->routeIs('websites.snippet.javascript')) {
                if ($authUser->pendingPublicAdministrations->where('id', $currentPublicAdministration->id)->isNotEmpty()) {
                    $authorized = true;
                }
            }

            if ($currentPublicAdministration->id !== $request->route('website')->publicAdministration->id) {
                $notFound = true;
            }
        }

        if ($request->route('user')) {
            if (!$request->route('user')->publicAdministrationsWithSuspended->contains($currentPublicAdministration)) {
                $notFound = true;
            }
        }

        if ($request->route('credential')) {
            if ($currentPublicAdministration->id !== $request->route('credential')->publicAdministration->id) {
                $notFound = true;
            }
        }

        if ($notFound ?? false) {
            abort(404);
        }

        if (!($authorized ?? false)) {
            abort(403);
        }

        return $next($request);
    }
}
