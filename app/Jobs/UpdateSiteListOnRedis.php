<?php

namespace App\Jobs;

use App\Contracts\AnalyticsService;
use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateSiteListOnRedis implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Analytics Service.
     *
     * @var AnalyticsService
     */
    protected $analyticsService;

    /**
     * Default constructor.
     */
    public function __construct()
    {
        $this->analyticsService = app()->make('analytics-service');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $websiteList = Website::all();
        /*
            per ogni website predere l'id e fare una chiamata a matomo per prendere la lista dei domini
            145 sito1 sito2
        */

        foreach ($websiteList as $website) {
            $id = $website->analytics_id;
            $list = $this->analyticsService->getSiteUrlsFromId($id);

            $listToString = implode(' ', $list);

            Cache::put($id, $listToString);
        }

        logger()->notice(
            'Caching websites for public administrations'
        );
    }
}
