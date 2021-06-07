<?php
/*
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|

Route::prefix(config('app.api_version'))->group(function () {
    Route::middleware('api.auth')->group(function () {

        Route::get('/', function () {
            return response()->json(['test' => getallheaders()], 200);
        });
        Route::prefix('/users')->group(function () {
            Route::get('/', 'UserController@indexApi')
                ->name('api.users');
            Route::post('/', 'UserController@storeApi')
                ->name('api.users.store');
        Route::get('/{fn}', 'UserController@showApi')
                ->name('api.users.show');
            Route::put('/{fn}', 'UserController@updateApi')
                ->name('api.users.update');
            Route::patch('/{fn}/suspend', 'UserController@suspend')
                ->name('api.users.suspend');
            Route::patch('/{fn}/reactivate', 'UserController@reactivate')
                ->name('api.users.reactivate');
        });
        Route::prefix('/websites')->group(function () {
            Route::post('/', 'WebsiteController@storeApi')
                ->name('api.websites.add');
            Route::get('/', 'WebsiteController@dataApi')
                ->name('api.websites.show');
            Route::get('/{website}', 'WebsiteController@show')
                ->name('api.websites.read');
            Route::get('/list/{id}', 'WebsiteController@websiteList')
                ->name('api.websites.websites');
            Route::put('/{website}', 'WebsiteController@update')
                ->name('api.websites.update');
            Route::patch('/{website}/archive', 'WebsiteController@archive')
                ->name('api.websites.archive');
            Route::patch('/{website}/unarchive', 'WebsiteController@unarchive')
                ->name('api.websites.unarchive');
            Route::get('/{website}/check', 'WebsiteController@checkTracking')
                ->name('api.websites.check');
            Route::get('/{website}/force', 'WebsiteController@forceActivation')
                ->name('api.websites.force');
            Route::get('/{website}/js-snippet', 'WebsiteController@showJavascriptSnippet')
                ->name('api.websites.snippet.javascript');

        });
    });
});

*/
