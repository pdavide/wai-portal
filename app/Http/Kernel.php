<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ScopeBouncer::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],

        'cron' => [
            \App\Http\Middleware\CronAuthenticate::class,
            'throttle:60,1',
            'bindings',
        ],

        'hooks' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth.admin' => \App\Http\Middleware\AuthenticateAdmin::class,
        'auth' => \App\Http\Middleware\Authenticate::class,
        'authorize.public.administrations' => \App\Http\Middleware\AuthorizePublicAdministration::class,
        'authorize.analytics' => \App\Http\Middleware\AuthorizeAnalytics::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'select.tenant' => \App\Http\Middleware\SelectTenant::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.not.expired' => \App\Http\Middleware\EnsurePasswordIsNotExpired::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'cron.auth' => \App\Http\Middleware\CronAuthenticate::class,
        'enforce.rules' => \App\Http\Middleware\EnforceRule::class,
        'api.auth' => \App\Http\Middleware\AuthenticateApi::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    protected $middlewarePriority = [
        \Illuminate\Session\Middleware\StartSession::class,
        /* \Illuminate\View\Middleware\ShareErrorsFromSession::class, */
        \App\Http\Middleware\ScopeBouncer::class,
        \Italia\SPIDAuth\Middleware::class,
        \App\Http\Middleware\Authenticate::class,
        \App\Http\Middleware\AuthenticateAdmin::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
        \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        \App\Http\Middleware\EnsurePasswordIsNotExpired::class,
        \App\Http\Middleware\SelectTenant::class,
        \App\Http\Middleware\AuthorizeAnalytics::class,
        \App\Http\Middleware\AuthenticateApi::class,
    ];
}
