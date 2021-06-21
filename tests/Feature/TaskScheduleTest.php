<?php

namespace Tests\Feature;

use App\Jobs\MonitorWebsitesTracking;
use App\Jobs\ProcessPendingWebsites;
use App\Jobs\ProcessPublicAdministrationsUpdateFromIpa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Task scheduling tests.
 */
class TaskScheduleTest extends TestCase
{
    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    /**
     * Post-test cleanup.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test public administrations index update.
     */
    public function testPublicAdministrationsUpdateFromIpa(): void
    {
        $scheduledStart = Carbon::now()->setHour(6)->setMinutes(30);
        Carbon::setTestNow($scheduledStart);
        $this->artisan('schedule:run');

        Bus::assertDispatched(ProcessPublicAdministrationsUpdateFromIpa::class);
    }

    /**
     * Test pending websites check.
     */
    public function testProcessPendingWebsites(): void
    {
        $scheduledStart = Carbon::now()->setMinutes(00);
        Carbon::setTestNow($scheduledStart);
        $this->artisan('schedule:run');

        Bus::assertDispatched(ProcessPendingWebsites::class, function ($job) {
            return !$job->executePurgeCheck;
        });
    }

    /**
     * Test pending websites with purge check.
     */
    public function testProcessPendingWebsitesWithPurge(): void
    {
        $scheduledStart = Carbon::now()->setHour(4)->setMinutes(30);
        Carbon::setTestNow($scheduledStart);
        $this->artisan('schedule:run');

        Bus::assertDispatched(ProcessPendingWebsites::class, function ($job) {
            return $job->executePurgeCheck;
        });
    }

    /**
     * Test active websites tracking check.
     */
    public function testMonitorWebsitesTracking(): void
    {
        $scheduledStart = Carbon::now()->setHour(0)->setMinutes(00);
        Carbon::setTestNow($scheduledStart);
        $this->artisan('schedule:run');

        Bus::assertDispatched(MonitorWebsitesTracking::class);
    }
}
