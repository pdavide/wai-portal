<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Events\User\UserEmailChanged;
use App\Events\User\UserStatusChanged;
use App\Models\User;
use App\Services\MatomoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * User updates events listener tests.
 */
class UserUpdatesSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Updating user.
     *
     * @var User the user
     */
    private $user;

    /**
     * Pre-test setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        Event::fake([UserEmailChanged::class, UserStatusChanged::class]);

        $this->user = factory(User::class)->create();
    }

    /**
     * Test user updating event handler expecting email verification reset.
     */
    public function testUpdatingWithEmailVerificationReset(): void
    {
        Event::fakeFor(function () {
            $this->user->email_verified_at = Date::now();
            $this->user->save();
        });

        $this->user->email = 'newfake@email.local';
        $this->user->save();

        $this->assertNull($this->user->email_verified_at);
    }

    /**
     * Test user updating event handler not expecting email verification reset.
     */
    public function testUpdatingWithoutEmailVerificationReset(): void
    {
        Event::fakeFor(function () {
            $this->user->email_verified_at = Date::now();
            $this->user->save();
        });

        $this->user->name = 'Fakename';
        $this->user->save();

        $this->assertNotNull($this->user->email_verified_at);
        $this->assertEquals('Fakename', $this->user->name);
    }

    /**
     * Test user with analytics account email verification updated event handler.
     */
    public function testUpdatedEmailVerifiedWithAnalyticsAccount(): void
    {
        Event::fakeFor(function () {
            $this->user->partial_analytics_password = 'fakepassword';
            $this->user->save();
        });

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getUserAuthToken')
                    ->withArgs([
                        $this->user->uuid,
                        md5($this->user->analytics_password),
                    ])
                    ->andReturn('faketoken');
                $mock->shouldReceive('updateUserEmail')
                    ->withArgs([
                        $this->user->uuid,
                        $this->user->email,
                        $this->user->analytics_password,
                        'faketoken',
                    ]);
            });
        });

        $this->user->email_verified_at = Date::now();
        $this->user->save();

        Event::assertNotDispatched(UserEmailChanged::class);

        Event::assertNotDispatched(UserStatusChanged::class);
    }

    /**
     * Test user without analytics account email verification updated event handler.
     */
    public function testUpdatedEmailVerifiedWithoutAnalyticsAccount(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class)
                ->shouldNotReceive('updateUserEmail');
        });

        $this->user->email_verified_at = Date::now();
        $this->user->save();

        Event::assertNotDispatched(UserEmailChanged::class);

        Event::assertNotDispatched(UserStatusChanged::class);
    }

    /**
     * Test user with analytics account email updated event handler.
     */
    public function testUpdatedEmailChangedWithAnalyticsAccount(): void
    {
        Event::fakeFor(function () {
            $this->user->partial_analytics_password = 'fakepassword';
            $this->user->save();
        });

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getUserAuthToken')
                    ->withArgs([
                        $this->user->uuid,
                        md5($this->user->analytics_password),
                    ])
                    ->once()
                    ->andReturn('faketoken');
                $mock->shouldReceive('updateUserEmail')
                    ->withArgs([
                        $this->user->uuid,
                        'newfake@email.local',
                        $this->user->analytics_password,
                        'faketoken',
                    ])
                    ->once();
            });
        });

        $this->user->email = 'newfake@email.local';
        $this->user->save();

        Event::assertDispatched(UserEmailChanged::class, function ($event) {
            return 'newfake@email.local' === $event->getUser()->email;
        });

        Event::assertNotDispatched(UserStatusChanged::class);
    }

    /**
     * Test user without analytics account email updated event handler.
     */
    public function testUpdatedEmailChangedWithoutAnalyticsAccount(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class)
                ->shouldNotReceive('updateUserEmail');
        });

        $this->user->email = 'newfake@email.local';
        $this->user->save();

        Event::assertDispatched(UserEmailChanged::class, function ($event) {
            return 'newfake@email.local' === $event->getUser()->email;
        });

        Event::assertNotDispatched(UserStatusChanged::class);
    }

    /**
     * Test user status changed event handler.
     */
    public function testUpdatedStatusChanged(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class)
                ->shouldNotReceive('updateUserEmail');
        });

        $oldStatus = $this->user->status;

        $this->user->status = UserStatus::ACTIVE;
        $this->user->save();

        Event::assertNotDispatched(UserEmailChanged::class);

        Event::assertDispatched(UserStatusChanged::class, function ($event) use ($oldStatus) {
            $eventUser = $event->getUser();

            return $this->user->uuid === $eventUser->uuid
                && $eventUser->status->is(UserStatus::ACTIVE)
                && $event->getOldStatus()->is($oldStatus);
        });
    }
}
