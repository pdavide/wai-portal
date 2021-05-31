<?php

namespace App\Providers;

use App\Services\KongClientService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Analytics service provider.
 */
class KongClientProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('kong-client-service', function ($app) {
            return new KongClientService();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array the provided services array
     */
    public function provides(): array
    {
        return ['kong-client-service'];
    }
}
