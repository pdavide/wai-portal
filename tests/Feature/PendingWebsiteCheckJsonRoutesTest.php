<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserStatus;
use App\Enums\WebsiteStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use GuzzleHttp\Client as TrackingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Website controller JSON requests test.
 */
class PendingWebsiteCheckJsonRoutesTest extends TestCase
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
     * @throws \App\Exceptions\AnalyticsServiceAccountException if the Analytics Service account doesn't exist
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect to the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command finishes with error
     * @throws \App\Exceptions\TenantIdNotSetException if the tenant id is not set in the current session
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->state('active')->create();
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->publicAdministration->users()->sync([$this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE]]);

        $this->website = factory(Website::class)->create([
            'status' => WebsiteStatus::PENDING,
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $analyticsID = $this->app->make('analytics-service')->registerSite($this->website->name, $this->website->url, $this->publicAdministration->name);
        $this->website->analytics_id = $analyticsID;
        $this->website->save();
        session()->put('tenant_id', $this->publicAdministration->id);

        Bouncer::dontCache();
        Bouncer::scope()->to($this->publicAdministration->id);
        $this->user->allow(UserPermission::MANAGE_WEBSITES);
        $this->user->registerAnalyticsServiceAccount();
        $this->user->setWriteAccessForWebsite($this->website);
        $this->user->syncWebsitesPermissionsToAnalyticsService($this->publicAdministration);

        Event::fake();
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
     * Test website status not changed.
     */
    public function testCheckWebsiteNotActiveRoute(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('get', route('websites.tracking.check', ['website' => $this->website->slug]));

        $response->assertStatus(303);
        $response->assertExactJson([]);
    }

    /**
     * Test website activated.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException if unable to inject a tracking request
     */
    public function testCheckWebsiteActiveRoute(): void
    {
        $client = new TrackingClient(['base_uri' => config('analytics-service.api_base_uri')]);
        $client->request('GET', 'piwik.php', [
            'query' => [
                'rec' => '1',
                'idsite' => $this->website->analytics_id,
            ],
            'verify' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('get', route('websites.tracking.check', ['website' => $this->website->slug]));

        $response->assertJson([
            'result' => 'ok',
            'website_name' => $this->website->name,
            'id' => $this->website->slug,
            'status' => WebsiteStatus::getKey(WebsiteStatus::ACTIVE),
            'status_description' => WebsiteStatus::getDescription(WebsiteStatus::ACTIVE),
        ]);
    }

    /**
     * Test fail request due to missing website.
     */
    public function testCheckWebsiteFailedRoute(): void
    {
        do {
            $website = factory(Website::class)->make([
                'public_administration_id' => $this->publicAdministration->id,
                'status' => WebsiteStatus::PENDING,
            ]);
        } while ($website->slug === $this->website->slug);

        $website->save();

        $this->user->setViewAccessForWebsite($website);

        $response = $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('get', route('websites.tracking.check', ['website' => $website->slug]));

        $response->assertJson([
            'result' => 'error',
            'message' => 'Bad Request',
        ]);
    }
}
