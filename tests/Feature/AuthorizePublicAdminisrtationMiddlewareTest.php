<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Italia\SPIDAuth\SPIDUser;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Analytics authorization middleware tests.
 */
class AuthorizePublicAdministrationMiddlewareTest extends TestCase
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

    /* Fake data generator.
     *
     * @var Generator the generator
     */
    private $faker;

    /**
     * Pre-test setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create([
            'status' => UserStatus::PENDING,
            'email_verified_at' => Date::now(),
        ]);

        $this->faker = Factory::create();
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->publicAdministration->users()->sync([$this->user->id => [
            'user_status' => UserStatus::ACTIVE,
            'user_email' => $this->faker->unique()->safeEmail,
        ]]);

        $this->spidUser = new SPIDUser([
            'fiscalNumber' => $this->user->fiscal_number,
            'familyName' => $this->user->family_name,
            'name' => $this->user->name,
        ]);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->allow(UserPermission::MANAGE_USERS);
            $this->user->allow(UserPermission::MANAGE_ANALYTICS, $this->website);
        });

        Bouncer::dontCache();
    }

    /**
     * Test user authorization on public administration granted.
     */
    public function testPermissionOnPublicAdministrationGranted(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
            ])
            ->get(route('websites.index'))
            ->assertStatus(200);
    }

    /**
     * Test user access to its only public administration without tenant id: granted.
     */
    public function testPermissionOnPublicAdministrationOnlyOnePAGranted(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'spid_user' => $this->spidUser,
            ])
            ->get(route('websites.index'))
            ->assertStatus(200);
    }

    /**
     * Test user access to one of its public administrations without tenant id: failed.
     */
    public function testPermissionOnPublicAdministrationFailed(): void
    {
        $secondPublicAdministration = factory(PublicAdministration::class)->create();
        $secondPublicAdministration->users()->sync([$this->user->id => [
            'user_status' => UserStatus::ACTIVE,
            'user_email' => $this->faker->unique()->safeEmail,
        ]]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'spid_user' => $this->spidUser,
            ])
            ->get(route('websites.index'))
            ->assertRedirect(route('publicAdministrations.show'));
    }

    /**
     * Test user suspended authorization on public administration failed.
     */
    public function testSuspendedPermissionOnPublicAdministrationFailed(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => Date::now(),
        ]);

        $this->publicAdministration->users()->sync([$user->id => [
            'user_status' => UserStatus::SUSPENDED,
            'user_email' => $this->faker->unique()->safeEmail,
        ]]);

        $spidUser = new SPIDUser([
            'fiscalNumber' => $user->fiscal_number,
            'familyName' => $user->family_name,
            'name' => $user->name,
        ]);

        $this->actingAs($user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $spidUser,
            ])
            ->get(route('websites.index'))
            ->assertRedirect(route('publicAdministrations.show'));
    }
}
