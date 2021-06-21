<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Jobs\MonitorWebsitesTracking;
use App\Jobs\ProcessPendingWebsites;
use App\Jobs\ProcessPublicAdministrationsUpdateFromIpa;
use App\Jobs\ProcessUsersIndex;
use App\Jobs\ProcessWebsitesIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Custom application commands tests.
 */
class CommandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    /**
     * Test check pending website command.
     */
    public function testCheckPendingWebsites(): void
    {
        $this->artisan('app:check-websites');

        Bus::assertDispatched(ProcessPendingWebsites::class, function ($job) {
            return !$job->executePurgeCheck;
        });

        $this->artisan('app:check-websites -D');

        Bus::assertDispatched(ProcessPendingWebsites::class, function ($job) {
            return $job->executePurgeCheck;
        });

        $this->artisan('app:check-websites --execute-purge-check');

        Bus::assertDispatched(ProcessPendingWebsites::class, function ($job) {
            return $job->executePurgeCheck;
        });
    }

    /**
     * Test create roles command.
     */
    public function testCreateRoles(): void
    {
        $this->assertDatabaseMissing('roles', [
            'name' => UserRole::REGISTERED,
            'name' => UserRole::DELEGATED,
            'name' => UserRole::ADMIN,
            'name' => UserRole::SUPER_ADMIN,
        ]);
        $this->assertDatabaseMissing('abilities', [
            'name' => UserPermission::ACCESS_ADMIN_AREA,
            'name' => UserPermission::MANAGE_USERS,
            'name' => UserPermission::MANAGE_WEBSITES,
            'name' => UserPermission::MANAGE_ANALYTICS,
            'name' => UserPermission::READ_ANALYTICS,
            'name' => UserPermission::DO_NOTHING,
        ]);
        $this->artisan('app:init-permissions');
        $this->assertDatabaseHas('roles', [
            'name' => UserRole::REGISTERED,
            'name' => UserRole::DELEGATED,
            'name' => UserRole::ADMIN,
            'name' => UserRole::SUPER_ADMIN,
        ]);
        $this->assertDatabaseHas('abilities', [
            'name' => UserPermission::ACCESS_ADMIN_AREA,
            'name' => UserPermission::MANAGE_USERS,
            'name' => UserPermission::MANAGE_WEBSITES,
            'name' => UserPermission::MANAGE_ANALYTICS,
            'name' => UserPermission::READ_ANALYTICS,
            'name' => UserPermission::DO_NOTHING,
        ]);
    }

    /**
     * Test active websites tracking check command.
     */
    public function testMonitorWebsiteTracking(): void
    {
        $this->artisan('app:monitor-activity');

        Bus::assertDispatched(MonitorWebsitesTracking::class);
    }

    /**
     * Test update IPA command.
     */
    public function testUpdatePublicAdministrationsFromIpa(): void
    {
        Bus::fake();

        $this->artisan('app:update-ipa');

        Bus::assertDispatched(ProcessPublicAdministrationsUpdateFromIpa::class);
    }

    /**
     * Test users index update command.
     */
    public function testUpdateUsersIndex(): void
    {
        $this->artisan('app:update-users');

        Bus::assertDispatched(ProcessUsersIndex::class);
    }

    /**
     * Test websites index update command.
     */
    public function testUpdateWebsitesIndex(): void
    {
        $this->artisan('app:update-websites');

        Bus::assertDispatched(ProcessWebsitesIndex::class);
    }
}
