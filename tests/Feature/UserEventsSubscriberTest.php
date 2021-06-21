<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Events\User\UserActivated;
use App\Events\User\UserDeleted;
use App\Events\User\UserEmailChanged;
use App\Events\User\UserInvited;
use App\Events\User\UserLogin;
use App\Events\User\UserLogout;
use App\Events\User\UserReactivated;
use App\Events\User\UserStatusChanged;
use App\Events\User\UserSuspended;
use App\Events\User\UserUpdated;
use App\Events\User\UserWebsiteAccessChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Notifications\ActivatedEmail;
use App\Notifications\PasswordChangedEmail;
use App\Notifications\PasswordResetRequestEmail;
use App\Notifications\ReactivatedEmail;
use App\Notifications\SuspendedEmail;
use App\Notifications\UserActivatedEmail;
use App\Notifications\UserInvitedEmail;
use App\Notifications\UserReactivatedEmail;
use App\Notifications\UserSuspendedEmail;
use App\Notifications\UserWebsiteAccessChangedEmail;
use App\Notifications\VerifyEmail;
use App\Traits\InteractsWithRedisIndex;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * User events listener tests.
 */
class UserEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        Notification::fake();
        Bouncer::dontCache();
    }

    public function testUserSuspended(): void
    {
        $secondUser = factory(User::class)->state('active')->create();
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();

        $publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::ACTIVE]], false);
        $publicAdministration->users()->sync([$secondUser->id => ['user_status' => UserStatus::ACTIVE]], false);

        $publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::SUSPENDED]], false);

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($secondUser) {
            $this->user->assign(UserRole::ADMIN);
            $secondUser->assign(UserRole::ADMIN);
        });

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' suspended.',
            [
                'user' => $this->user->uuid,
                'event' => EventType::USER_SUSPENDED,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserSuspended($this->user, $publicAdministration));

        Notification::assertSentTo(
            [$this->user],
            SuspendedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($mail->subject, __('Utente sospeso'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$secondUser],
            UserSuspendedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->user->uuid, $mail->viewData['suspendedUser']['uuid']);
                $this->assertEquals($mail->subject, __('Utente sospeso'));

                return $mail->hasTo($secondUser->email, $secondUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserSuspendedEmail::class
        );
    }

    public function testUserRegistered(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($this->user);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->expectLogMessage('notice', [
            'New user registered: ' . $this->user->uuid,
            [
                'event' => EventType::USER_REGISTERED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new Registered($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Verifica email per :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testSuperAdminPasswordResetRequested(): void
    {
        $this->user->sendPasswordResetRequestNotification('faketoken');

        Notification::assertSentTo(
            [$this->user],
            PasswordResetRequestEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('faketoken', $mail->viewData['token']);
                $this->assertEquals($mail->subject, __('Reset della password'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testSuperAdminPasswordResetCompleted(): void
    {
        $this->expectLogMessage('notice', [
            'Password successfully changed for user ' . $this->user->uuid,
            [
                'event' => EventType::USER_PASSWORD_RESET_COMPLETED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new PasswordReset($this->user));

        Notification::assertSentTo(
            [$this->user],
            PasswordChangedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($mail->subject, __('Password modificata'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testUserEmailVerified(): void
    {
        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' confirmed email address.',
            [
                'event' => EventType::USER_VERIFIED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new Verified($this->user));
    }

    public function testUserActivated(): void
    {
        $secondUser = factory(User::class)->state('active')->create();
        $thirdUser = factory(User::class)->state('active')->create();
        $fourthUser = factory(User::class)->state('active')->create();
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $publicAdministration->users()->sync([$this->user->id, $secondUser->id, $thirdUser->id, $fourthUser->id]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($secondUser, $thirdUser) {
            $secondUser->assign(UserRole::ADMIN);
            $thirdUser->assign(UserRole::ADMIN);
            $this->user->assign(UserRole::ADMIN);
        });

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' activated',
            [
                'event' => EventType::USER_ACTIVATED,
                'user' => $this->user->uuid,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserActivated($this->user, $publicAdministration));

        Notification::assertSentTo(
            [$this->user],
            ActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($mail->subject, __('Utente attivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$secondUser],
            UserActivatedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->user->uuid, $mail->viewData['activatedUser']['uuid']);
                $this->assertEquals($mail->subject, __('Utente attivato'));

                return $mail->hasTo($secondUser->email, $secondUser->full_name);
            }
        );

        Notification::assertSentTo(
            [$thirdUser],
            UserActivatedEmail::class,
            function ($notification, $channels) use ($thirdUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($thirdUser)->build();
                $this->assertEquals($thirdUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->user->uuid, $mail->viewData['activatedUser']['uuid']);
                $this->assertEquals($mail->subject, __('Utente attivato'));

                return $mail->hasTo($thirdUser->email, $thirdUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserActivatedEmail::class
        );

        Notification::assertNotSentTo(
            [$fourthUser],
            UserActivatedEmail::class
        );
    }

    public function testUserInvited(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $userInvited = factory(User::class)->state('invited')->create();
        $publicAdministration->users()->sync([$this->user->id, $userInvited->id]);

        Event::fakeFor(function () {
            $this->user->status = UserStatus::ACTIVE;
            $this->user->save();
        });

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($userInvited) {
            $this->user->assign(UserRole::ADMIN);
            $this->user->allow(UserPermission::MANAGE_USERS);
            $userInvited->assign(UserRole::DELEGATED);
        });

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($userInvited);

        $this->expectLogMessage('notice', [
            'New user invited: ' . $userInvited->uuid . ' by ' . $this->user->uuid,
            [
                'event' => EventType::USER_INVITED,
                'user' => $userInvited->uuid,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserInvited($userInvited, $this->user, $publicAdministration));

        Notification::assertSentTo(
            [$userInvited],
            VerifyEmail::class,
            function ($notification, $channels) use ($publicAdministration, $userInvited) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($userInvited)->build();
                $this->assertEquals($userInvited->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertEquals($publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($mail->subject, __('Invito su :app', ['app' => config('app.name')]));

                return $mail->hasTo($userInvited->email, $userInvited->full_name);
            }
        );

        Notification::assertSentTo(
            [$this->user],
            UserInvitedEmail::class,
            function ($notification, $channels) use ($publicAdministration, $userInvited) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($userInvited->uuid, $mail->viewData['invitedUser']['uuid']);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($mail->subject, __('Nuovo utente invitato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testSuperAdminInvited(): void
    {
        $userInvited = factory(User::class)->state('invited')->create();
        Bouncer::scope()->onceTo(0, function () use ($userInvited) {
            $this->user->assign(UserRole::SUPER_ADMIN);
            $this->user->allow(UserPermission::ACCESS_ADMIN_AREA);
            $userInvited->assign(UserRole::SUPER_ADMIN);
            $userInvited->allow(UserPermission::ACCESS_ADMIN_AREA);
        });

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'admin.verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($userInvited);

        $this->expectLogMessage('notice', [
            'New user invited: ' . $userInvited->uuid . ' by ' . $this->user->uuid,
            [
                'event' => EventType::USER_INVITED,
                'user' => $userInvited->uuid,
            ],
        ]);

        event(new UserInvited($userInvited, $this->user));

        Notification::assertSentTo(
            [$userInvited],
            VerifyEmail::class,
            function ($notification, $channels) use ($userInvited) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($userInvited)->build();
                $this->assertEquals($userInvited->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Invito su :app', ['app' => config('app.name')]));

                return $mail->hasTo($userInvited->email, $userInvited->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserInvitedEmail::class
        );
    }

    public function testUserDeleted(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $publicAdministration->users()->sync([$this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE]]);
        $this->expectLogMessage(
            'notice',
            [
                'User ' . $this->user->uuid . ' deleted.',
                [
                    'event' => EventType::USER_DELETED,
                    'user' => $this->user->uuid,
                    'pa' => $publicAdministration->ipa_code,
                ],
            ]
        );

        event(new UserDeleted($this->user, $publicAdministration));
    }

    public function testUserReactivated(): void
    {
        $secondUser = factory(User::class)->state('active')->create();
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::SUSPENDED]], false);
        $publicAdministration->users()->sync([$secondUser->id => ['user_status' => UserStatus::SUSPENDED]], false);

        $publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::ACTIVE]], false);

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($secondUser) {
            $this->user->assign(UserRole::ADMIN);
            $secondUser->assign(UserRole::ADMIN);
        });

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' reactivated.',
            [
                'user' => $this->user->uuid,
                'event' => EventType::USER_REACTIVATED,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserReactivated($this->user, $publicAdministration));

        Notification::assertSentTo(
            [$this->user],
            ReactivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($mail->subject, __('Utente riattivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$secondUser],
            UserReactivatedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->user->uuid, $mail->viewData['reactivatedUser']['uuid']);
                $this->assertEquals($mail->subject, __('Utente riattivato'));

                return $mail->hasTo($secondUser->email, $secondUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserReactivatedEmail::class
        );
    }

    public function testUserUpdated(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($this->user);

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' updated',
            [
                'event' => EventType::USER_UPDATED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new UserUpdated($this->user));
    }

    public function testUserEmailChanged(): void
    {
        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' email address changed',
            [
                'event' => EventType::USER_EMAIL_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Verifica email per :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testSuperAdminEmailChanged(): void
    {
        Bouncer::scope()->onceTo(0, function () {
            $this->user->assign(UserRole::SUPER_ADMIN);
        });

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' email address changed',
            [
                'event' => EventType::USER_EMAIL_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'admin.verification.verify'
        )->andReturn('fakeverificationurl.local');

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Verifica email per :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testInvitedUserEmailChanged(): void
    {
        Event::fakeFor(function () {
            $this->user->status = UserStatus::INVITED;
            $this->user->save();
        });
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $publicAdministration->users()->sync([$this->user->id]);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' email address changed',
            [
                'event' => EventType::USER_EMAIL_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) use ($publicAdministration) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertEquals($publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals(__('Invito su :app', ['app' => config('app.name')]), $mail->subject);

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testInvitedSuperAdminEmailChanged(): void
    {
        Event::fakeFor(function () {
            $this->user->status = UserStatus::INVITED;
            $this->user->save();
        });

        Bouncer::scope()->onceTo(0, function () {
            $this->user->assign(UserRole::SUPER_ADMIN);
        });

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' email address changed',
            [
                'event' => EventType::USER_EMAIL_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'admin.verification.verify'
        )->andReturn('fakeverificationurl.local');

        event(new UserEmailChanged($this->user));

        Notification::assertSentTo(
            [$this->user],
            VerifyEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Invito su :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testUserWebsiteAccessChanged(): void
    {
        Event::fakeFor(function () {
            $this->user->status = UserStatus::ACTIVE;
            $this->user->save();
        });
        $secondUser = factory(User::class)->create();
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        $accessType = WebsiteAccessType::fromValue(WebsiteAccessType::VIEW);
        $publicAdministration->users()->sync([$this->user->id, $secondUser->id]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () {
            $this->user->assign(UserRole::ADMIN);
        });

        $this->expectLogMessage(
            'notice',
            [
                'Granted "' . $accessType->description . '" access for website ' . $website->info . ' to user ' . $secondUser->uuid,
                [
                    'event' => EventType::USER_WEBSITE_ACCESS_CHANGED,
                    'user' => $secondUser->uuid,
                    'pa' => $publicAdministration->ipa_code,
                    'website' => $website->id,
                ],
            ]
        );

        event(new UserWebsiteAccessChanged($secondUser, $website, $accessType));

        // Notification disabled
        // Notification::assertSentTo(
        //     [$this->user],
        //     UserWebsiteAccessChangedEmail::class,
        //     function ($notification, $channels) use ($secondUser) {
        //         $this->assertEquals($channels, ['mail']);
        //         $mail = $notification->toMail($this->user)->build();
        //         $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
        //         $this->assertEquals($secondUser->uuid, $mail->viewData['modifiedUser']['uuid']);
        //         $this->assertEquals($mail->subject, __('Permessi utente modificati'));

        //         return $mail->hasTo($this->user->email, $this->user->full_name);
        //     }
        // );
    }

    public function testUserStatusChanged(): void
    {
        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' status changed from "' . UserStatus::getDescription(UserStatus::PENDING) . '" to "' . $this->user->status->description . '"',
            [
                'event' => EventType::USER_STATUS_CHANGED,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new UserStatusChanged($this->user, UserStatus::PENDING));
    }

    public function testUserLogin(): void
    {
        Event::fake(UserUpdated::class);

        $this->expectLogMessage('info', [
            'User ' . $this->user->uuid . ' logged in.',
            [
                'user' => $this->user->uuid,
                'event' => EventType::USER_LOGIN,
            ],
        ]);

        event(new UserLogin($this->user));
    }

    public function testUserLogout(): void
    {
        $this->expectLogMessage('info', [
            'User ' . $this->user->uuid . ' logged out.',
            [
                'user' => $this->user->uuid,
                'event' => EventType::USER_LOGOUT,
            ],
        ]);

        event(new UserLogout($this->user));
    }
}
