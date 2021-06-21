<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteType;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteDeleted;
use App\Events\Website\WebsiteRestored;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Super-admin website CRUD test.
 */
class CRUDAdminWebsiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

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
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $this->website = factory(Website::class)->make([
            'public_administration_id' => $this->publicAdministration->id,
            'type' => WebsiteType::INFORMATIONAL,
        ]);
        $analyticsId = $this->app->make('analytics-service')->registerSite($this->website->name . ' [' . $this->website->type->value . ']', $this->website->url, $this->publicAdministration->name);
        $this->website->analytics_id = $analyticsId;
        $this->website->save();

        $this->user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => Date::now(),
        ]);

        Bouncer::dontCache();
        Bouncer::scope()->to(0);
        $this->user->assign(UserRole::SUPER_ADMIN);
        $this->user->allow(UserPermission::MANAGE_WEBSITES);
        $this->user->allow(UserPermission::ACCESS_ADMIN_AREA);
    }

    /**
     * Post-test tear down.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     */
    protected function tearDown(): void
    {
        $this->app->make('analytics-service')->deleteSite($this->website->analytics_id);
        parent::tearDown();
    }

    /**
     * Test JSON data for public administration websites datatable successful.
     */
    public function testPublicAdministrationWebsiteDatatableData(): void
    {
        $this->actingAs($this->user)
            ->json('GET', route('admin.publicAdministration.websites.data.json', [
                'publicAdministration' => $this->publicAdministration,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'raw' => e($this->website->name),
            ]);
    }

    /**
     * Test website creation successful.
     */
    public function testStoreWebsiteSuccessful(): void
    {
        $this->actingAs($this->user)
            ->from(route('admin.publicAdministration.websites.create', [
                'publicAdministration' => $this->publicAdministration,
            ]))
            ->post(route('admin.publicAdministration.websites.store', [
                'publicAdministration' => $this->publicAdministration,
            ]), [
                'website_name' => 'Sito tematico',
                'url' => 'https://www.test.local',
                'type' => WebsiteType::INFORMATIONAL,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.publicAdministration.websites.index', [
                'publicAdministration' => $this->publicAdministration,
            ]));

        $createdWebsite = Website::where('slug', Str::slug('https://www.test.local'))->first();
        $this->app->make('analytics-service')->deleteSite($createdWebsite->analytics_id);

        Event::assertDispatched(WebsiteAdded::class, function ($event) {
            return $event->getWebsite()->slug === Str::slug('https://www.test.local');
        });
    }

    /**
     * Test website creation fail due to fields validation.
     */
    public function testStoreWebsiteFailValidation(): void
    {
        $this->actingAs($this->user)
            ->from(route('admin.publicAdministration.websites.create', [
                'publicAdministration' => $this->publicAdministration,
            ]))
            ->post(route('admin.publicAdministration.websites.store', [
                'publicAdministration' => $this->publicAdministration,
            ]), [
                'url' => 'www.camera.it',
                'type' => WebsiteType::INSTITUTIONAL,
            ])
            ->assertSessionHasErrors([
                'website_name',
                'url',
                'type',
            ])
            ->assertRedirect(route('admin.publicAdministration.websites.create', [
                'publicAdministration' => $this->publicAdministration,
            ]));

        Event::assertNotDispatched(WebsiteAdded::class);
    }

    /**
     * Test website delete successful.
     */
    public function testDeleteWebsiteSuccessful(): void
    {
        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->website,
            ]))
            ->assertOk();

        $this->website->refresh();

        $this->assertTrue($this->website->trashed());

        Event::assertDispatched(WebsiteDeleted::class, function ($event) {
            return $this->website->slug === $event->getWebsite()->slug;
        });
    }

    /**
     * Test website delete fail due to primary website.
     */
    public function testDeleteWebsitePrimaryFail(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::INSTITUTIONAL;
            $this->website->save();
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->website,
            ]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Delete request not allowed on primary website ' . $this->website->info . '.',
            ]);

        $this->website->refresh();

        $this->assertFalse($this->website->trashed());

        Event::assertNotDispatched(WebsiteDeleted::class);
    }

    /**
     * Test website restore successful.
     */
    public function testRestoreWebsiteSuccessful(): void
    {
        Event::fakeFor(function () {
            $this->website->delete();
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->website,
            ]))
            ->assertOk();

        $this->website->refresh();

        $this->assertFalse($this->website->trashed());

        Event::assertDispatched(WebsiteRestored::class, function ($event) {
            return $this->website->slug === $event->getWebsite()->slug;
        });
    }
}
