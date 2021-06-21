<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Analytics authorization middleware tests.
 */
class AuthorizeAnalyticsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The authenticated user.
     *
     * @var User the user
     */
    private $user;

    /**
     * The public administration the user belongs to.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * The public administration website.
     *
     * @var Website the website
     */
    private $website;

    /**
     * Pre-test setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();

        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->publicAdministration->users()->sync([$this->user->id]);

        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->allow(UserPermission::MANAGE_USERS);
            $this->user->allow(UserPermission::MANAGE_ANALYTICS, $this->website);
        });

        Bouncer::dontCache();

        Route::middleware('authorize.analytics:' . UserPermission::VIEW_LOGS)->get('_test/view-logs', function () {
            return Response::HTTP_OK;
        });

        Route::middleware(['authorize.analytics:' . UserPermission::MANAGE_USERS, 'web'])->get('_test/{user}/view-user', function (User $user) {
            return Response::HTTP_OK;
        });

        Route::middleware(['authorize.analytics:' . UserPermission::MANAGE_ANALYTICS, 'web'])->get('_test/{website}/view-website', function (Website $website) {
            return Response::HTTP_OK;
        });

        Route::middleware(['authorize.analytics:' . UserPermission::MANAGE_ANALYTICS, 'web'])->get('_test/{website}/check-tracking', function (Website $website) {
            return Response::HTTP_OK;
        })->name('websites.tracking.check');

        Route::middleware(['authorize.analytics:' . UserPermission::MANAGE_ANALYTICS, 'web'])->get('_test/{website}/get-snippet', function (Website $website) {
            return Response::HTTP_OK;
        })->name('websites.snippet.javascript');
    }

    /**
     * Test user authorization fail.
     */
    public function testMissingPermissionAuthorizationFail(): void
    {
        $this->actingAs($this->user)
            ->get('_test/view-logs')
            ->assertForbidden();
    }

    /**
     * Test authorization granted.
     */
    public function testPermissionAuthorizationGranted(): void
    {
        $this->user->allow(UserPermission::VIEW_LOGS);

        $this->actingAs($this->user)
            ->get('_test/view-logs')
            ->assertOk();
    }

    /**
     * Test users routes authorization fail due to wrong public administration.
     */
    public function testDifferentPublicAdministrationUsersRouteAuthorizationFail(): void
    {
        $secondUser = factory(User::class)->create();
        $secondPublicAdministration = factory(PublicAdministration::class)->create();
        $secondPublicAdministration->users()->sync([$secondUser->id]);

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondUser->uuid . '/view-user')
            ->assertForbidden();
    }

    /**
     * Test users routes authorization granted.
     */
    public function testUsersRouteAuthorizationGranted(): void
    {
        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $this->user->uuid . '/view-user')
            ->assertOk();
    }

    /**
     * Test websites routes authorization granted.
     */
    public function testWebsitesRouteAuthorizationGranted(): void
    {
        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $this->website->slug . '/view-website')
            ->assertOk();
    }

    /**
     * Test websites routes authorization fail due to missing permission on website.
     */
    public function testMissingAuthorizationWebsitesRouteAuthorizationFail(): void
    {
        do {
            $secondWebsite = factory(Website::class)->make([
                'public_administration_id' => $this->publicAdministration->id,
            ]);
        } while ($secondWebsite->slug === $this->website->slug);
        $secondWebsite->save();

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($secondWebsite) {
            $this->user->allow(UserPermission::READ_ANALYTICS, $secondWebsite);
        });

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/view-website')
            ->assertForbidden();
    }

    /**
     * Test tracking check and JS snippet routes authorization granted for pending user.
     */
    public function testPendingUserWebsitesRouteAuthorizationGranted(): void
    {
        $this->user->email_verified_at = Date::now();
        $this->user->save();
        $this->publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::PENDING]]);

        do {
            $secondWebsite = factory(Website::class)->make([
                'public_administration_id' => $this->publicAdministration->id,
            ]);
        } while ($secondWebsite->slug === $this->website->slug);
        $secondWebsite->save();

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/check-tracking')
            ->assertOk();

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/get-snippet')
            ->assertOk();
    }

    /**
     * Test tracking check and JS snippet routes authorization fail due to not pending user.
     */
    public function testNotPendingUserWebsitesRouteAuthorizationFail(): void
    {
        do {
            $secondWebsite = factory(Website::class)->make([
                'public_administration_id' => $this->publicAdministration->id,
            ]);
        } while ($secondWebsite->slug === $this->website->slug);
        $secondWebsite->save();

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/check-tracking')
            ->assertForbidden();

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/get-snippet')
            ->assertForbidden();
    }

    /**
     * Test websites routes authorization fail due to wrong public administration.
     */
    public function testDifferentPublicAdministrationWebsiteRoutesAuthorizationFail(): void
    {
        $secondPublicAdministration = factory(PublicAdministration::class)->create();
        do {
            $secondWebsite = factory(Website::class)->make([
                'public_administration_id' => $secondPublicAdministration->id,
            ]);
        } while ($secondWebsite->slug === $this->website->slug);
        $secondWebsite->save();

        Bouncer::scope()->onceTo($secondPublicAdministration->id, function () use ($secondWebsite) {
            $this->user->allow(UserPermission::MANAGE_ANALYTICS, $secondWebsite);
        });

        $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->publicAdministration->id])
            ->get('_test/' . $secondWebsite->slug . '/view-website')
            ->assertForbidden();
    }
}
