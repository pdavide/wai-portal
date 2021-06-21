<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Events\User\UserInvited;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Traits\InteractsWithRedisIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * User invited events listener tests.
 */
class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The inviting user.
     *
     * @var User the user
     */
    private $user;

    /**
     * The invited user.
     *
     * @var User the user
     */
    private $userInvited;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->userInvited = factory(User::class)->state('invited')->create();
        $this->user = factory(User::class)->state('active')->create();

        Bouncer::dontCache();
        Notification::fake();
    }

    /**
     * Test user invited event handler.
     */
    public function testUserInvited(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $publicAdministration->users()->sync([$this->user->id, $this->userInvited->id]);

        Bouncer::scope()->onceTo($publicAdministration->id, function () {
            $this->user->assign(UserRole::ADMIN);
            $this->user->allow(UserPermission::MANAGE_USERS);
            $this->userInvited->assign(UserRole::DELEGATED);
        });

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($this->userInvited);

        $this->expectLogMessage('notice', [
            'New user invited: ' . $this->userInvited->uuid . ' by ' . $this->user->uuid,
            [
                'event' => EventType::USER_INVITED,
                'user' => $this->userInvited->uuid,
                'pa' => $publicAdministration->ipa_code,
            ],
        ]);

        event(new UserInvited($this->userInvited, $this->user, $publicAdministration));

        Notification::assertSentTo(
            [$this->userInvited],
            VerifyEmail::class,
            function ($notification, $channels) use ($publicAdministration) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->userInvited)->build();
                $this->assertEquals($this->userInvited->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertEquals($publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($mail->subject, __('Account su :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->userInvited->email);
            }
        );
    }

    /**
     * Test super-admin invited event handler.
     */
    public function testSuperAdminInvited(): void
    {
        Bouncer::scope()->onceTo(0, function () {
            $this->user->assign(UserRole::SUPER_ADMIN);
            $this->user->allow(UserPermission::ACCESS_ADMIN_AREA);
            $this->userInvited->assign(UserRole::SUPER_ADMIN);
            $this->userInvited->allow(UserPermission::ACCESS_ADMIN_AREA);
        });

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'admin.verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($this->userInvited);

        $this->expectLogMessage('notice', [
            'New user invited: ' . $this->userInvited->uuid . ' by ' . $this->user->uuid,
            [
                'event' => EventType::USER_INVITED,
                'user' => $this->userInvited->uuid,
            ],
        ]);

        event(new UserInvited($this->userInvited, $this->user));

        Notification::assertSentTo(
            [$this->userInvited],
            VerifyEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->userInvited)->build();
                $this->assertEquals($this->userInvited->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Invito su :app', ['app' => config('app.name')]));

                return $mail->hasTo($this->userInvited->email);
            }
        );
    }
}
