<?php

namespace Tests\Unit;

use App\Enums\PublicAdministrationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Events\PublicAdministration\PublicAdministrationActivated;
use App\Events\User\UserActivated;
use App\Events\User\UserWebsiteAccessChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Services\MatomoService;
use App\Traits\ActivatesWebsite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Website activation trait tests.
 */
class ActivatesWebsiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The anonymous class using the trait.
     *
     * @var mixed the anonymous class
     */
    private $class;

    /**
     * The public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * The website.
     *
     * @var Website the website
     */
    private $website;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->class = new class() {
            use ActivatesWebsite;
        };
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 1,
        ]);
    }

    /**
     * Test website activation check expecting "not active".
     */
    public function testHasActivated(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getLiveVisits')
                    ->withArgs([
                        $this->website->analytics_id,
                        60,
                    ])
                    ->once()
                    ->andReturn(0);
                $mock->shouldReceive('getSiteTotalVisitsFrom')
                    ->withArgs([
                        $this->website->analytics_id,
                        $this->website->created_at->format('Y-m-d'),
                    ])
                    ->once()
                    ->andReturn(0);
            });
        });

        $result = $this->class->hasActivated($this->website);

        $this->assertFalse($result);
    }

    /**
     * Test website activation check expecting "active" due to live visits.
     */
    public function testHasActivatedLiveVisits(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getLiveVisits')
                    ->withArgs([
                        $this->website->analytics_id,
                        60,
                    ])
                    ->once()
                    ->andReturn(1);
                $mock->shouldReceive('getSiteTotalVisitsFrom')
                    ->withArgs([
                        $this->website->analytics_id,
                        $this->website->created_at->format('Y-m-d'),
                    ])
                    ->once()
                    ->andReturn(0);
            });
        });

        $result = $this->class->hasActivated($this->website);

        $this->assertTrue($result);
    }

    /**
     * Test website activation check expecting "active" due to archived visits.
     */
    public function testHasActivatedTotalVisits(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getLiveVisits')
                    ->withArgs([
                        $this->website->analytics_id,
                        60,
                    ])
                    ->once()
                    ->andReturn(0);
                $mock->shouldReceive('getSiteTotalVisitsFrom')
                    ->withArgs([
                        $this->website->analytics_id,
                        $this->website->created_at->format('Y-m-d'),
                    ])
                    ->once()
                    ->andReturn(1);
            });
        });

        $result = $this->class->hasActivated($this->website);

        $this->assertTrue($result);
    }

    /**
     * Test primary website activation.
     */
    public function testActivatedPrimaryWebsite(): void
    {
        Event::fake();
        Bouncer::dontCache();

        $user = factory(User::class)->state('pending')->create();
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::PENDING]]);

        $this->app->bind('analytics-service', function () use ($user) {
            return $this->partialMock(MatomoService::class, function ($mock) use ($user) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->withArgs([
                        $user->uuid,
                        WebsiteAccessType::WRITE,
                        $this->website->analytics_id,
                    ])
                    ->once();
            });
        });

        $this->class->activate($this->website);

        Event::assertDispatched(UserWebsiteAccessChanged::class, function ($event) use ($user) {
            return $user->uuid === $event->getUser()->uuid
                && $this->website->id === $event->getWebsite()->id
                && $event->getAccessType()->is(WebsiteAccessType::WRITE);
        });

        Event::assertDispatched(PublicAdministrationActivated::class, function ($event) {
            return $this->publicAdministration->id === $event->getPublicAdministration()->id;
        });

        Event::assertDispatched(UserActivated::class, function ($event) use ($user) {
            return $user->id === $event->getUser()->id;
        });

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($user) {
            $this->assertTrue($user->isA(UserRole::ADMIN));
            $this->assertTrue($user->can(UserPermission::READ_ANALYTICS, $this->website));
            $this->assertTrue($user->can(UserPermission::MANAGE_ANALYTICS, $this->website));
        });

        $this->publicAdministration->refresh();
        $user->refresh();
        $this->assertTrue($this->publicAdministration->status->is(PublicAdministrationStatus::ACTIVE));
        $this->assertTrue($this->website->status->is(WebsiteStatus::ACTIVE));
        $this->assertTrue($user->status->is(UserStatus::ACTIVE));
    }

    /**
     * Test secondary website activation.
     */
    public function testActivatedSecondaryWebsite(): void
    {
        Event::fake();
        Bouncer::dontCache();

        $this->publicAdministration->status = PublicAdministrationStatus::ACTIVE;
        $this->publicAdministration->save();

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldNotReceive('setWebsiteAccess');
            });
        });

        $this->class->activate($this->website);

        Event::assertNotDispatched(UserWebsiteAccessChanged::class);

        Event::assertNotDispatched(PublicAdministrationActivated::class);

        Event::assertNotDispatched(UserActivated::class);

        $this->assertTrue($this->website->status->is(WebsiteStatus::ACTIVE));
    }
}
