<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * General public routes
 */

Route::get('/', [
    'as' => 'home',
    'uses' => 'HomeController@home'
]);

Route::get('/privacy', [
    'as' => 'privacy',
    'uses' => 'HomeController@home'
]);

Route::get('/faq', [
    'as' => 'faq',
    'uses' => 'HomeController@faq'
]);

/**
 * Authentication related routes
 */

/** Only SPID authentication without application authentication */
Route::prefix('/register')->middleware(['spid.auth', 'guest'])->group(function() {
    Route::get('/', [
        'as' => 'auth-register',
        'uses' => 'Auth\RegisterController@index'
    ]);

    Route::post('/', [
        'as' => 'auth-do_register',
        'uses' => 'Auth\RegisterController@register'
    ]);
});

/** Public route to allow token verification from application form or email link */
//Route::get('/verify/token/{token?}', [
//    'as' => 'auth-do_verify',
//    'uses' => 'Auth\VerificationController@verifyToken'
//]);

/** Both SPID and application authentication: this is the default for registered users */
Route::prefix('/verify')->middleware('spid.auth')->group(function() {
    Route::get('/', [
        'as' => 'auth-verify',
        'uses' => 'Auth\VerificationController@verify'
    ]);

    Route::get('/token/{token?}', [
        'as' => 'auth-do_verify',
        'uses' => 'Auth\VerificationController@verifyToken'
    ]);

    Route::get('/resend', [
        'as' => 'auth-verify_resend',
        'uses' => 'Auth\VerificationController@resend'
    ]);
});

/**
 * Registered-only application routes
 */

/** Both SPID and application authentication: this is the default for registered users */
Route::middleware(['spid.auth', 'auth'])->group(function() {
    Route::prefix('/dashboard')->group(function() {
        Route::get('/', [
            'as' => 'dashboard',
            'uses' => 'DashboardController@index'
        ]);

        Route::post('/search-ipa-list', [
            'as' => 'search-ipa-list',
            'uses' => 'SearchIPAListController@search'
        ]);

        Route::post('/public-administrations', [
            'as' => 'public-administrations-store',
            'uses' => 'PublicAdministrationController@store'
        ]);

        Route::get('/add-primary-website', [
            'as' => 'add-primary-website',
            'uses' => 'DashboardController@addPrimaryWebsite'
        ]);

        Route::get('/websites', [
            'as' => 'websites-index',
            'uses' => 'WebsiteController@index'
        ])->middleware('authorize.analytics:read-analytics');

        Route::get('/websites/{website}/javascript-snippet', [
            'as' => 'website-javascript-snippet',
            'uses' => 'WebsiteController@showJavascriptSnippet'
        ])->middleware('authorize.analytics:read-analytics');

        Route::get('/websites/data', [
            'as' => 'websites-data-json',
            'uses' => 'WebsiteController@dataJson'
        ])->middleware('authorize.analytics:read-analytics');

        Route::get('/websites/add-website', [
            'as' => 'websites-create',
            'uses' => 'WebsiteController@create'
        ])->middleware('authorize.analytics:manage-sites');

        Route::post('/websites', [
            'as' => 'websites-store',
            'uses' => 'WebsiteController@store'
        ])->middleware('authorize.analytics:manage-sites');

        Route::get('/users', [
            'as' => 'users-index',
            'uses' => 'UserController@index'
        ])->middleware('authorize.analytics:read-analytics');

        Route::get('/users/data', [
            'as' => 'users-data-json',
            'uses' => 'UserController@dataJson'
        ])->middleware('authorize.analytics:read-analytics');

        Route::get('/users/add-user', [
            'as' => 'users-create',
            'uses' => 'UserController@create'
        ])->middleware('authorize.analytics:manage-users');

        Route::post('/users', [
            'as' => 'users-store',
            'uses' => 'UserController@store'
        ])->middleware('authorize.analytics:manage-users');
    });
    Route::prefix('/analytics-service')->group(function() {
        Route::get('/login', [
            'as' => 'analytics-service-login',
            'uses' => 'AnalyticsController@login'
        ]);
    });
});
