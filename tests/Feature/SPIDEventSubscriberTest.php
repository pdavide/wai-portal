<?php

namespace Tests\Feature;

use App\Events\User\UserLogin;
use App\Events\User\UserLogout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\Events\LogoutEvent;
use Italia\SPIDAuth\SPIDUser;
use Tests\TestCase;

/**
 * SPID events listener tests.
 */
class SPIDEventSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test SPID login event handler with not registered user.
     */
    public function testSpidLoginNoUser(): void
    {
        Event::fake([UserLogin::class]);
        $spidUser = new SPIDUser([
            'fiscalNumber' => ['TINIT-BRGLRZ80D58H501Q'],
            'familyName' => ['Surname'],
            'name' => ['Name'],
        ]);
        event(new LoginEvent($spidUser, 'testIdP'));

        $this->assertNull(auth()->user());
        Event::assertNotDispatched(UserLogin::class);
    }

    /**
     * Test SPID login event handler with registered user.
     */
    public function testSpidLoginRegisteredUser(): void
    {
        Event::fake([UserLogin::class]);
        $user = factory(User::class)->create();

        $spidUser = new SPIDUser([
            'fiscalNumber' => ['TINIT-' . $user->fiscal_number],
            'familyName' => [$user->family_name],
            'name' => [$user->name],
        ]);
        event(new LoginEvent($spidUser, 'testIdP'));

        Event::assertDispatched(UserLogin::class, function ($event) use ($user) {
            return $event->getUser()->uuid === $user->uuid;
        });

        $this->assertEquals($user->uuid, auth()->user()->uuid);
    }

    /**
     * Test SPID logout event handler.
     */
    public function testSpidLogout(): void
    {
        Event::fake(UserLogout::class);
        $user = factory(User::class)->create();

        auth()->login($user);

        $spidUser = new SPIDUser([
            'fiscalNumber' => ['TINIT-' . $user->fiscal_number],
            'familyName' => [$user->family_name],
            'name' => [$user->name],
        ]);
        event(new LogoutEvent($spidUser, 'testIdP'));

        Event::assertDispatched(UserLogout::class, function ($event) use ($user) {
            return $event->getUser()->uuid === $user->uuid;
        });

        //NOTE: auth()->user() still exists
        $this->assertEmpty(session()->all());
    }
}
