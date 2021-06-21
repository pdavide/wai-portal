<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Traits\InteractsWithRedisIndex;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * User registered events listener tests.
 */
class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registered event handler.
     */
    public function testUserRegistration(): void
    {
        Notification::fake();
        $user = factory(User::class)->create();

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateUsersIndex')
            ->with($user);

        URL::shouldReceive('temporarySignedRoute')->once()->withSomeOfArgs(
            'verification.verify'
        )->andReturn('fakeverificationurl.local');

        $this->expectLogMessage('notice', [
            'New user registered: ' . $user->uuid,
            [
                'event' => EventType::USER_REGISTERED,
                'user' => $user->uuid,
            ],
        ]);

        event(new Registered($user));

        Notification::assertSentTo(
            [$user],
            VerifyEmail::class,
            function ($notification, $channels) use ($user) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($user)->build();
                $this->assertEquals($user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals('fakeverificationurl.local', $mail->viewData['signedUrl']);
                $this->assertNull($mail->viewData['publicAdministration']);
                $this->assertEquals($mail->subject, __('Account su :app', ['app' => config('app.name')]));

                return $mail->hasTo($user->email);
            }
        );
    }
}
