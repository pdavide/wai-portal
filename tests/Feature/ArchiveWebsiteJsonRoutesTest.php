<?php

namespace Tests\Feature;

use App\Enums\PublicAdministrationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteUnarchived;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Website controller archive/re-enable JSON requests tests.
 */
class ArchiveWebsiteJsonRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The fake calling user.
     *
     * @var User the user
     */
    protected $user;

    /**
     * The selected public administration for the user.
     *
     * @var PublicAdministration the public administration
     */
    protected $publicAdministration;

    /**
     * The requested website.
     *
     * @var Website the website
     */
    protected $website;

    /**
     * Pre-test setup.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect to the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command finishes with error
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     * @throws \App\Exceptions\TenantIdNotSetException if the tenant id is not set in the current session
     */
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bouncer::dontCache();

        $this->user = factory(User::class)->state('active')->create();
        $this->publicAdministration = factory(PublicAdministration::class)->create([
            'status' => PublicAdministrationStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE]]);
        $this->website = factory(Website::class)->create([
            'status' => WebsiteStatus::ACTIVE,
            'type' => WebsiteType::INFORMATIONAL,
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $analyticsID = $this->app->make('analytics-service')->registerSite($this->website->name, $this->website->url, $this->publicAdministration->name);
        $this->website->analytics_id = $analyticsID;
        $this->website->save();

        $this->user->roles()->detach();
        Bouncer::scope()->to($this->publicAdministration->id);
        $this->user->assign(UserRole::ADMIN);
        $this->user->allow(UserPermission::MANAGE_WEBSITES);

        session()->put('tenant_id', $this->publicAdministration->id);
        $this->user->registerAnalyticsServiceAccount();
        $this->user->setWriteAccessForWebsite($this->website);
        $this->user->syncWebsitesPermissionsToAnalyticsService($this->publicAdministration);
    }

    /**
     * Post-test cleanup.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect to the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command finishes with error
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    protected function tearDown(): void
    {
        $this->user->deleteAnalyticsServiceAccount();
        $this->app->make('analytics-service')->deleteSite($this->website->analytics_id);
        parent::tearDown();
    }

    /**
     * Test website archive status not modified response.
     */
    public function testArchiveWebsiteNotChangedRoute(): void
    {
        $this->website->status = WebsiteStatus::ARCHIVED;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.archive', ['website' => $this->website->slug]));

        $response->assertStatus(303);
        $response->assertExactJson([]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

    /**
     * Test website archive successfully completed.
     */
    public function testArchiveWebsiteChangedRoute(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.archive', ['website' => $this->website->slug]));

        $response->assertStatus(200);

        $response->assertJson([
            'result' => 'ok',
            'id' => $this->website->slug,
            'website_name' => $this->website->name,
            'status' => WebsiteStatus::getKey(WebsiteStatus::ARCHIVED),
            'status_description' => WebsiteStatus::getDescription(WebsiteStatus::ARCHIVED),
        ]);

        Event::assertDispatched(WebsiteArchived::class, function ($event) {
            return $event->getWebsite()->slug === $this->website->slug;
        });
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

    /**
     * Test website archive failed due to primary website type.
     */
    public function testArchiveFailOnPrimarySiteRoute(): void
    {
        $this->website->type = WebsiteType::INSTITUTIONAL;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.archive', ['website' => $this->website->slug]));

        $response->assertStatus(400);

        $response->assertJson([
            'result' => 'error',
            'message' => 'Invalid operation for current website',
        ]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

    /**
     * Test website archive failed due to wrong current status.
     */
    public function testArchiveWebsiteFailedWrongStatusRoute(): void
    {
        $this->website->status = WebsiteStatus::PENDING;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.archive', ['website' => $this->website->slug]));

        $response->assertStatus(400);

        $response->assertJson([
            'result' => 'error',
            'message' => 'Invalid operation for current website status',
        ]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

//    to be uncommented after the resolution of https://github.com/matomo-org/matomo/issues/8697
//    /**
//     * Test website archive failed due to error in Analytics Service call.
//     */
//    public function testArchiveWebsiteFailedRoute(): void
//    {
//        $website = factory(Website::class)->create([
//            'public_administration_id' => $this->publicAdministration->id,
//            'type' => WebsiteType::INFORMATIONAL,
//            'status' => WebsiteStatus::ACTIVE,
//        ]);
//
//        $response = $this->actingAs($this->user)
//            ->withSession([
//                'spid_sessionIndex' => 'fake-session-index',
//                'tenant_id' => $this->publicAdministration->id,
//            ])
//            ->json('patch', route('websites.archive', ['website' => $website->slug]));
//
//        $response->assertJson([
//            'result' => 'error',
//            'message' => 'Bad Request',
//        ]);
//
//        Event::assertNotDispatched(WebsiteArchived::class);
//        Event::assertNotDispatched(WebsiteUnarchived::class);
//    }

    /**
     * Test website re-enable status not modified response.
     */
    public function testUnarchiveWebsiteNotChanged(): void
    {
        $this->website->status = WebsiteStatus::ACTIVE;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.unarchive', ['website' => $this->website->slug]));

        $response->assertStatus(303);
        $response->assertExactJson([]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

    /**
     * Test website re-enable successfully completed.
     */
    public function testUnarchiveWebsiteChangedRoute(): void
    {
        $this->website->status = WebsiteStatus::ARCHIVED;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.unarchive', ['website' => $this->website->slug]));

        $response->assertStatus(200);

        $response->assertJson([
            'result' => 'ok',
            'id' => $this->website->slug,
            'website_name' => $this->website->name,
            'status' => WebsiteStatus::getKey(WebsiteStatus::ACTIVE),
            'status_description' => WebsiteStatus::getDescription(WebsiteStatus::ACTIVE),
        ]);

        Event::assertDispatched(WebsiteUnarchived::class, function ($event) {
            return $event->getWebsite()->slug === $this->website->slug;
        });
        Event::assertNotDispatched(WebsiteArchived::class);
    }

    /**
     * Test website re-enable failed due to primary website type.
     */
    public function testUnarchiveFailOnPrimarySiteRoute(): void
    {
        $this->website->type = WebsiteType::INSTITUTIONAL;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.unarchive', ['website' => $this->website->slug]));

        $response->assertStatus(400);

        $response->assertJson([
            'result' => 'error',
            'message' => 'Invalid operation for current website',
        ]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

    /**
     * Test website re-enable failed due to wrong current status.
     */
    public function testUnarchiveWebsiteFailedWrongStatusRoute(): void
    {
        $this->website->status = WebsiteStatus::PENDING;
        $this->website->save();

        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('websites.unarchive', ['website' => $this->website->slug]));

        $response->assertStatus(400);

        $response->assertJson([
            'result' => 'error',
            'message' => 'Invalid operation for current website status',
        ]);

        Event::assertNotDispatched(WebsiteArchived::class);
        Event::assertNotDispatched(WebsiteUnarchived::class);
    }

//    to be uncommented after the resolution of https://github.com/matomo-org/matomo/issues/8697
//    /**
//     * Test website archive failed due to error in Analytics Service call.
//     */
//    public function testUnarchiveWebsiteFailedRoute(): void
//    {
//        $website = factory(Website::class)->create([
//            'public_administration_id' => $this->publicAdministration->id,
//            'status' => WebsiteStatus::ARCHIVED,
//            'type' => WebsiteType::INFORMATIONAL,
//        ]);
//
//        $response = $this->actingAs($this->user)
//            ->withSession([
//                'spid_sessionIndex' => 'fake-session-index',
//                'tenant_id' => $this->publicAdministration->id,
//            ])
//            ->json('patch', route('websites.unarchive', ['website' => $website->slug]));
//
//        $response->assertJson([
//            'result' => 'error',
//            'message' => 'Bad Request',
//        ]);
//
//        Event::assertNotDispatched(WebsiteArchived::class);
//        Event::assertNotDispatched(WebsiteUnarchived::class);
//    }
}
