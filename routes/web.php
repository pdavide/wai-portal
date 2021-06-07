<?php

use App\Enums\UserPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group.
|
| Defined routes:
| - general public routes;
| - authentication routes;
| - registration routes;
| - email verification routes;
| - application routes;
| - admin-only application routes.
|
*/

/*
 * General public routes.
 */

Route::get('/', 'HomeController@home')
    ->name('home');

Route::get('/how-to-join', 'HomeController@howToJoin')
    ->name('how-to-join');

Route::get('/privacy', 'HomeController@privacy')
    ->name('privacy');

Route::get('/legal-notes', 'HomeController@legalNotes')
    ->name('legal-notes');

Route::get('/faq', 'HomeController@faq')
    ->name('faq');

Route::get('/contacts', 'HomeController@contacts')
    ->name('contacts');

// Route::get('/open-data', 'HomeController@openData')
//     ->name('open-data');

/*
 * Admin authentication routes.
 */
Route::prefix('/admin/user')->group(function () {
    Route::get('/login', 'Auth\SuperAdminAuthController@showLogin')
        ->name('admin.login.show')->middleware('enforce.rules:forbid-spid');

    Route::post('/login', 'Auth\SuperAdminAuthController@login')
        ->name('admin.login')->middleware('enforce.rules:forbid-spid');

    Route::get('/logout', 'Auth\SuperAdminAuthController@logout')
        ->name('admin.logout')->middleware('auth.admin');

    Route::get('/password-forgot', 'Auth\SuperAdminAuthController@showPasswordForgot')
        ->name('admin.password.forgot.show')->middleware('enforce.rules:forbid-spid');

    Route::post('/password-forgot', 'Auth\SuperAdminAuthController@sendPasswordForgot')
        ->name('admin.password.reset.send')->middleware('enforce.rules:forbid-spid', 'throttle:5,1');

    Route::get('/password-reset/{token?}', 'Auth\SuperAdminAuthController@showPasswordReset')
        ->name('admin.password.reset.show')->middleware('enforce.rules:forbid-spid');

    Route::post('/password-reset', 'Auth\SuperAdminAuthController@passwordReset')
        ->name('admin.password.reset')->middleware('enforce.rules:forbid-spid', 'throttle:5,1');
});

/*
 * Admin email verification routes.
 *
 * Application authentication required.
 */
Route::middleware('auth.admin')->group(function () {
    Route::prefix('/admin/user/verify')->group(function () {
        Route::get('/', 'Auth\VerificationController@show')
            ->name('admin.verification.notice');

        Route::get('/resend', 'Auth\VerificationController@resend')
            ->name('admin.verification.resend')->middleware('enforce.rules:forbid-invited', 'throttle:5,1');

        Route::get('/{uuid}/{hash}', 'Auth\VerificationController@verify')
            ->name('admin.verification.verify')->middleware('signed', 'throttle:5,1');
    });

    Route::middleware('enforce.rules:forbid-invited')->group(function () {
        Route::prefix('/admin/user/profile')->group(function () {
            Route::get('/', 'Auth\ProfileController@edit')
                ->name('admin.user.profile.edit');

            Route::patch('/', 'Auth\ProfileController@update')
                ->name('admin.user.profile.update');
        });
    });
});

/*
 * Registration routes.
 *
 * Only SPID authentication required.
 */
Route::middleware('spid.auth', 'guest')->group(function () {
    Route::prefix('/register')->group(function () {
        Route::get('/', 'Auth\RegisterController@showRegistrationForm')
            ->name('auth.register.show');

        Route::post('/', 'Auth\RegisterController@register')
            ->name('auth.register');
    });
});

/*
 * Email verification routes.
 *
 * SPID authentication required.
 */
Route::middleware('spid.auth', 'auth')->group(function () {
    Route::prefix('/user/verify')->group(function () {
        Route::get('/', 'Auth\VerificationController@show')
            ->name('verification.notice');

        Route::get('/resend', 'Auth\VerificationController@resend')
            ->name('verification.resend')->middleware('throttle:5,1');

        Route::get('/{uuid}/{hash}', 'Auth\VerificationController@verify')
            ->name('verification.verify')->middleware('signed', 'throttle:5,1');
    });
});

/*
 * User profile routes.
 *
 * Both SPID and application authentication required
 */
Route::middleware('spid.auth', 'auth')->group(function () {
    Route::prefix('/user/profile')->group(function () {
        Route::get('/', 'Auth\ProfileController@edit')
            ->name('user.profile.edit');

        Route::patch('/', 'Auth\ProfileController@update')
            ->name('user.profile.update');
    });
});

/*
 * Application routes.
 *
 * Both SPID and application authentication for verified users required.
 * This is the default for registered users.
 */
Route::middleware('spid.auth', 'auth', 'verified:verification.notice')->group(function () {
    Route::prefix('/public-administrations')->group(function () {
        Route::get('/', [
            'as' => 'publicAdministrations.show',
            'uses' => 'PublicAdministrationController@show',
        ]);

        Route::post('/select', [
            'as' => 'publicAdministrations.select',
            'uses' => 'PublicAdministrationController@selectTenant',
        ]);

        Route::post('/accept/{publicAdministration}', [
            'as' => 'publicAdministration.acceptInvitation',
            'uses' => 'PublicAdministrationController@acceptInvitation',
        ]);

        Route::get('/add', 'PublicAdministrationController@add')
            ->name('publicAdministrations.add');

        Route::get('/data', 'PublicAdministrationController@dataJson')
            ->name('publicAdministrations.data.json');
    });

    Route::get('/search-ipa-index', 'SearchIpaIndexController@search')
        ->name('ipa.search')
        ->middleware('throttle:60,1');

    Route::post('/store-primary', 'WebsiteController@storePrimary')
        ->name('websites.store.primary');

    Route::middleware('select.tenant', 'authorize.public.administrations')->group(function () {
        Route::get('/analytics', 'AnalyticsController@index')
        ->name('analytics');

        /*
        Route::get('/api', 'SwaggerController@index')
        ->name('show.swagger');

        Route::get('/api/specification', 'SwaggerController@apiSpecification')
        ->name('show.swagger.specification');

        Route::middleware('authorize.analytics:' . UserPermission::MANAGE_WEBSITES)->group(function () {
            Route::prefix('/api-credentials')->group(function () {
                Route::get('/', 'CredentialsController@index')
                ->name('api-credentials.index');

                Route::get('/create', 'CredentialsController@create')
                ->name('api-credentials.create');

                Route::post('/store', 'CredentialsController@store')
                ->name('api-credentials.store');

                Route::get('/data', 'CredentialsController@dataJson')
                ->name('api-credentials.data.json');

                Route::get('/data/permissions/{credential?}', 'CredentialsController@dataWebsitesPermissionsJson')
                ->name('api-credentials.websites.permissions');

                Route::get('/{credential}/show', 'CredentialsController@show')
                ->name('api-credentials.show');

                Route::get('/{credential}/regenerate', 'CredentialsController@regenerateCredential')
                ->name('api-credentials.regenerate');

                Route::get('/{credential}/show/json', 'CredentialsController@showJson')
                ->name('api-credentials.show-json');

                Route::get('/{credential}/edit', 'CredentialsController@edit')
                ->name('api-credentials.edit');

                Route::put('/{credential}', 'CredentialsController@update')
                ->name('api-credentials.update');

                Route::patch('/{credential}/delete', 'CredentialsController@delete')
                ->name('api-credentials.delete');
            });

        });

        */

        Route::middleware('authorize.analytics:' . UserPermission::VIEW_LOGS)->group(function () {
            Route::prefix('/logs')->group(function () {
                Route::get('/', 'Logs\LogController@show')
                ->name('logs.show');

                Route::get('/data', 'Logs\LogController@data')
                ->name('logs.data');

                Route::get('/search-website-index', 'Logs\SearchIndexController@searchWebsite')
                ->name('logs.websites.search')
                ->middleware('throttle:60,1');

                Route::get('/search-user-index', 'Logs\SearchIndexController@searchUser')
                ->name('logs.users.search')
                ->middleware('throttle:60,1');
            });
        });

        Route::prefix('/websites')->group(function () {
            Route::get('/', 'WebsiteController@index')
            ->name('websites.index');

            Route::get('/data', 'WebsiteController@dataJson')
            ->name('websites.data.json');

            Route::get('/{website}/show', 'WebsiteController@show')
            ->name('websites.show');

            Route::middleware('authorize.analytics:' . UserPermission::MANAGE_WEBSITES)->group(function () {
                // Authorization for specific websites is handled in the middleware
                Route::get('/create', 'WebsiteController@create')
                ->name('websites.create');

                Route::get('/users-data/{website?}', 'WebsiteController@dataUsersPermissionsJson')
                ->name('websites.users.permissions.data.json');

                Route::post('/store', 'WebsiteController@store')
                ->name('websites.store');

                Route::get('/{website}/edit', 'WebsiteController@edit')
                ->name('websites.edit');

                Route::put('/{website}', 'WebsiteController@update')
                ->name('websites.update');

                Route::get('/{website}/widgets/', 'WidgetsController@index')
                ->name('websites.show.widgets');

                Route::patch('/{website}/archive', 'WebsiteController@archive')
                ->name('websites.archive');

                Route::patch('/{website}/unarchive', 'WebsiteController@unarchive')
                ->name('websites.unarchive');

                Route::get('/{website}/check', 'WebsiteController@checkTracking')
                    ->name('websites.tracking.check');

                Route::get('/{website}/force', 'WebsiteController@forceActivation')
                    ->name('websites.activate.force');

                Route::get('/{website}/javascript-snippet', 'WebsiteController@showJavascriptSnippet')
                    ->name('websites.snippet.javascript');
            });
        });

        Route::prefix('/users')->group(function () {
            Route::get('/', 'UserController@index')
            ->name('users.index');

            Route::get('/data', 'UserController@dataJson')
            ->name('users.data.json');

            Route::middleware('authorize.analytics:' . UserPermission::MANAGE_USERS)->group(function () {
                Route::get('/create', 'UserController@create')
                ->name('users.create');

                Route::get('/websites-data/{user?}', 'UserController@dataWebsitesPermissionsJson')
                ->name('users.websites.permissions.data.json');

                Route::post('/', 'UserController@store')
                ->name('users.store');

                Route::get('/{user}/show', 'UserController@show')
                ->name('users.show');

                Route::get('/{user}/edit', 'UserController@edit')
                ->name('users.edit');

                Route::put('/{user}', 'UserController@update')
                ->name('users.update');

                Route::patch('/{user}/suspend', 'UserController@suspend')
                ->name('users.suspend');

                Route::patch('/{user}/reactivate', 'UserController@reactivate')
                ->name('users.reactivate');

                Route::get('/{user}/verification-resend', 'Auth\VerificationController@resend')
                ->name('users.verification.resend')->middleware('throttle:5,1');

                Route::get('/{user}/generate-credentials', 'UserController@generateCredentials')
                ->name('users.generate.credentials');
            });
        });

        Route::prefix('/analytics-service')->group(function () {
            Route::get('/login/{websiteAnalyticsId?}', 'AnalyticsServiceController@login')
            ->name('analytics.service.login');
        });
    });
});

/*
 * Admin-only application routes.
 *
 * Admin authentication required.
 */
Route::middleware('auth.admin', 'verified:admin.verification.notice')->group(function () {
    Route::prefix('/admin')->group(function () {
        Route::middleware('password.not.expired')->group(function () {
            Route::get('/', function () {
                return redirect()->route('admin.dashboard');
            });

            Route::prefix('/public-administrations')->group(function () {
                Route::post('/select', [
                    'as' => 'admin.publicAdministrations.select',
                    'uses' => 'PublicAdministrationController@selectTenant',
                ]);

                Route::get('/data', 'PublicAdministrationController@dataJson')
                    ->name('admin.publicAdministrations.data.json');
            });

            Route::get('/dashboard', 'SuperAdminDashboardController@dashboard')
                ->name('admin.dashboard');

            Route::prefix('/logs')->group(function () {
                Route::get('/', 'Logs\LogController@show')
                    ->name('admin.logs.show');

                Route::get('/data', 'Logs\LogController@data')
                    ->name('admin.logs.data');

                Route::get('/search-website-index', 'Logs\SearchIndexController@searchWebsite')
                    ->name('admin.logs.websites.search')
                    ->middleware('throttle:60,1');

                Route::get('/search-user-index', 'Logs\SearchIndexController@searchUser')
                    ->name('admin.logs.users.search')
                    ->middleware('throttle:60,1');
            });

            Route::prefix('/users')->group(function () {
                Route::get('/', 'SuperAdminUserController@index')
                    ->name('admin.users.index');

                Route::get('/data', 'SuperAdminUserController@dataJson')
                    ->name('admin.users.data.json');

                Route::get('/create', 'SuperAdminUserController@create')
                    ->name('admin.users.create');

                Route::post('/', 'SuperAdminUserController@store')
                    ->name('admin.users.store');

                Route::get('/{user}/show', 'SuperAdminUserController@show')
                    ->name('admin.users.show');

                Route::get('/{user}/edit', 'SuperAdminUserController@edit')
                    ->name('admin.users.edit');

                Route::put('/{user}/update', 'SuperAdminUserController@update')
                    ->name('admin.users.update');

                Route::patch('/{user}/suspend', 'SuperAdminUserController@suspend')
                    ->name('admin.users.suspend');

                Route::patch('/{user}/reactivate', 'SuperAdminUserController@reactivate')
                    ->name('admin.users.reactivate');

                Route::get('/{user}/verification-resend', 'Auth\VerificationController@resend')
                    ->name('admin.users.verification.resend')->middleware('throttle:5,1');
            });

            Route::prefix('/{publicAdministration}')->group(function () {
                Route::get('/analytics', 'AnalyticsController@index')
                    ->name('admin.publicAdministration.analytics');

                Route::prefix('/users')->group(function () {
                    Route::get('/', 'UserController@index')
                        ->name('admin.publicAdministration.users.index');

                    Route::get('/data', 'UserController@dataJson')
                        ->name('admin.publicAdministration.users.data.json');

                    Route::get('/create', 'UserController@create')
                        ->name('admin.publicAdministration.users.create');

                    Route::get('/websites-data/{user?}', 'UserController@dataWebsitesPermissionsJson')
                        ->name('admin.publicAdministration.users.websites.permissions.data.json');

                    Route::post('/', 'UserController@store')
                        ->name('admin.publicAdministration.users.store');

                    Route::get('/{user}/show', 'UserController@show')
                        ->name('admin.publicAdministration.users.show');

                    Route::get('/{user}/edit', 'UserController@edit')
                        ->name('admin.publicAdministration.users.edit');

                    Route::put('/{user}/update', 'UserController@update')
                        ->name('admin.publicAdministration.users.update');

                    Route::patch('/{user}/delete', 'UserController@delete')
                        ->name('admin.publicAdministration.users.delete');

                    Route::patch('/{trashed_user}/restore', 'UserController@restore')
                        ->name('admin.publicAdministration.users.restore');

                    Route::patch('/{user}/suspend', 'UserController@suspend')
                        ->name('admin.publicAdministration.users.suspend');

                    Route::patch('/{user}/reactivate', 'UserController@reactivate')
                        ->name('admin.publicAdministration.users.reactivate');

                    Route::get('/{user}/verification-resend', 'Auth\VerificationController@resend')
                        ->name('admin.publicAdministration.users.verification.resend')->middleware('throttle:5,1');

                    Route::get('/{user}/generate-credentials', 'UserController@generateCredentials')
                        ->name('admin.publicAdministration.users.generate.credentials');
                });

                Route::prefix('/websites')->group(function () {
                    Route::get('/', 'WebsiteController@index')
                        ->name('admin.publicAdministration.websites.index');

                    Route::get('/data', 'WebsiteController@dataJson')
                        ->name('admin.publicAdministration.websites.data.json');

                    Route::get('/create', 'WebsiteController@create')
                        ->name('admin.publicAdministration.websites.create');

                    Route::get('/users-data/{website?}', 'WebsiteController@dataUsersPermissionsJson')
                        ->name('admin.publicAdministration.websites.users.permissions.data.json');

                    Route::post('/store', 'WebsiteController@store')
                        ->name('admin.publicAdministration.websites.store');

                    Route::get('/{website}/show', 'WebsiteController@show')
                        ->name('admin.publicAdministration.websites.show');

                    Route::get('/{website}/edit', 'WebsiteController@edit')
                        ->name('admin.publicAdministration.websites.edit');

                    Route::put('/{website}', 'WebsiteController@update')
                        ->name('admin.publicAdministration.websites.update');

                    Route::patch('/{website}/delete', 'WebsiteController@delete')
                        ->name('admin.publicAdministration.websites.delete');

                    Route::patch('/{trashed_website}/restore', 'WebsiteController@restore')
                        ->name('admin.publicAdministration.websites.restore');

                    Route::patch('/{website}/archive', 'WebsiteController@archive')
                        ->name('admin.publicAdministration.websites.archive');

                    Route::patch('/{website}/unarchive', 'WebsiteController@unarchive')
                        ->name('admin.publicAdministration.websites.unarchive');

                    Route::get('/{website}/check', 'WebsiteController@checkTracking')
                        ->name('admin.publicAdministration.websites.tracking.check');

                    Route::get('/{website}/force', 'WebsiteController@forceActivation')
                        ->name('admin.publicAdministration.websites.activate.force');

                    Route::get('/{website}/javascript-snippet', 'WebsiteController@showJavascriptSnippet')
                        ->name('admin.publicAdministration.websites.snippet.javascript');
                });
            });

            Route::get('/sdg-current-dataset', 'HomeController@showCurrentSDGDataset')
                ->name('admin.sdg.dataset.show');
        });

        Route::get('/user/change-password', 'Auth\SuperAdminAuthController@showPasswordChange')
            ->name('admin.password.change.show');

        Route::post('/user/change-password', 'Auth\SuperAdminAuthController@passwordChange')
            ->name('admin.password.change');
    });
});
if (!request()->is('api/*')) {
    Route::fallback(function () {
        return response()->view('errors.404');
    });
}
