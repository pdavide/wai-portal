<?php

namespace App\Console;

use App\Jobs\MonitorWebsitesTracking;
use App\Jobs\ProcessPendingWebsites;
use App\Jobs\ProcessPublicAdministrationsUpdateFromIpa;
use App\Jobs\PurgePendingInvitations;
use App\Jobs\ResetEnvironment;
use App\Jobs\UpdateSiteListOnRedis;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Application command scheduler.
 */
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule the command scheduler
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new ProcessPublicAdministrationsUpdateFromIpa())->dailyAt('06:30')->onOneServer();
        $schedule->job(new ProcessPendingWebsites())->hourly()->onOneServer();
        // $schedule->job(new ProcessPendingWebsites(true))->dailyAt('04:30')->onOneServer();
        // $schedule->job(new MonitorWebsitesTracking())->daily()->onOneServer(); // DisableTracking plugin is currently not working properly
        // $schedule->job(new PurgePendingInvitations())->dailyAt('03:30')->onOneServer();
        $schedule->job(new UpdateSiteListOnRedis())->everyFiveMinutes()->onOneServer();
        // $publicPlaygroundResetTime = config('wai.reset_public_playground_hour', 23) . ':' . config('wai.reset_public_playground_minute', 30);
        // $schedule->job(new ResetEnvironment())->weekly()->days(config('wai.reset_public_playground_day', 0))->at($publicPlaygroundResetTime)->onOneServer()->environments(['public-playground']);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
