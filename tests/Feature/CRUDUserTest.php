<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Events\User\UserInvited;
use App\Events\User\UserReactivated;
use App\Events\User\UserSuspended;
use App\Events\User\UserUpdated;
use App\Events\User\UserWebsiteAccessChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Faker\Factory;
use Faker\Generator;
use Faker\Provider\it_IT\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Italia\SPIDAuth\SPIDUser;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * User CRUD test.
 */
class CRUDUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

    /**
     * The SPID user.
     *
     * @var SPIDUser the SPID user
     */
    private $spidUser;

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
     * Fake data generator.
     *
     * @var Generator the generator
     */
    private $faker;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => Date::now(),
        ]);
        $this->publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $this->publicAdministration->users()->sync([$this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE]]);

        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $this->spidUser = new SPIDUser([
            'fiscalNumber' => $this->user->fiscal_number,
            'familyName' => $this->user->family_name,
            'name' => $this->user->name,
        ]);

        Bouncer::dontCache();
        Bouncer::scope()->to($this->publicAdministration->id);
        $this->user->assign(UserRole::ADMIN);
        $this->user->allow(UserPermission::MANAGE_USERS);

        $this->faker = Factory::create();
        $this->faker->addProvider(new Person($this->faker));
    }

    /**
     * Test JSON data for datatable successful.
     */
    public function testDatatableData(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
            ])
            ->json('GET', route('users.data.json'))
            ->assertOk()
            ->assertJsonFragment([
                'raw' => e($this->user->full_name),
            ]);
    }

    /**
     * Test public administration admin creation successful.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    public function testCreateAdminUserSuccessful(): void
    {
        $email = $this->faker->unique()->freeEmail;
        $fiscalNumber = 'ESXLKY44P09I168D';

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->post(route('users.store'), [
                '_token' => 'test',
                'email' => $email,
                'fiscal_number' => $fiscalNumber,
                'is_admin' => '1',
                'permissions' => [
                    $this->website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'fiscal_number',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('users.index'));

        Event::assertDispatched(UserInvited::class, function ($event) use ($email) {
            return
                $email === $event->getUser()->email
                && $this->publicAdministration->ipa_code === $event->getPublicAdministration()->ipa_code
                && $this->user->is($event->getInvitedBy());
        });

        User::findNotSuperAdminByFiscalNumber($fiscalNumber)->deleteAnalyticsServiceAccount();
    }

    /**
     * Test public administration user creation and invitation successful.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    public function testCreateUserSuccessful(): void
    {
        $email = $this->faker->unique()->freeEmail;
        $fiscalNumber = 'ESXLKY44P09I168D';

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->post(route('users.store'), [
                '_token' => 'test',
                'email' => $email,
                'fiscal_number' => $fiscalNumber,
                'permissions' => [
                    $this->website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'fiscal_number',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('users.index'));

        Event::assertDispatched(UserInvited::class, function ($event) use ($email) {
            return
                $email === $event->getUser()->email
                && $this->publicAdministration->ipa_code === $event->getPublicAdministration()->ipa_code
                && $this->user->is($event->getInvitedBy());
        });

        User::findNotSuperAdminByFiscalNumber($fiscalNumber)->deleteAnalyticsServiceAccount();
    }

    /**
     * Test user creation fails due to fields validation.
     */
    public function testCreateUserFailValidation(): void
    {
        $fiscalNumber = 'XNTMDF63C44D878E';

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->from(route('users.create'))
            ->post(route('users.store'), [
                '_token' => 'test',
                'email' => $this->user->email,
                'fiscal_number' => $fiscalNumber,
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'email',
                'permissions',
            ]);

        Event::assertNotDispatched(UserInvited::class);
    }

    /**
     * Test user email update successful.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    public function testUpdateUserEmailSuccessful(): void
    {
        $this->user->registerAnalyticsServiceAccount();
        $analyticsId = app()->make('analytics-service')->registerSite($this->website->name . ' [' . $this->website->type . ']', $this->website->url, $this->publicAdministration->name);
        $this->website->analytics_id = $analyticsId;
        $this->website->save();
        app()->make('analytics-service')->setWebsiteAccess($this->user->uuid, WebsiteAccessType::WRITE, $this->website->analytics_id);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->put(route('users.update', ['user' => $this->user]), [
                '_token' => 'test',
                'email' => 'new@webanalytics.italia.it',
                'emailPublicAdministrationUser' => 'updated@webanalytics.italia.it',
                'is_admin' => '1',
                'permissions' => [
                    $this->website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'is_admin',
                'permissions',
            ]);

        $this->user->refresh();
        Event::assertDispatched(UserUpdated::class, function ($event) {
            return 'updated@webanalytics.italia.it' === $event->getUser()->getEmailforPublicAdministration($this->publicAdministration);
        });

        $this->user->deleteAnalyticsServiceAccount();
        app()->make('analytics-service')->deleteSite($this->website->analytics_id);
    }

    /**
     * Test public administration user change role to admin from delegate successful.
     */
    public function testUpdateUserFiscalNumberSuccessful(): void
    {
        $user = factory(User::class)->state('invited')->create();
        $fiscalNumber = 'SKYLKU77E25H501R';
        $this->publicAdministration->users()->sync([$user->id], false);

        $user->registerAnalyticsServiceAccount();

        Bouncer::scope()->to($this->publicAdministration->id);
        $user->assign(UserRole::DELEGATED);
        $user->allow(UserPermission::READ_ANALYTICS, $this->website);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->put(route('users.update', ['user' => $user]), [
                '_token' => 'test',
                'email' => $user->email,
                'emailPublicAdministrationUser' => 'old@webanalytics.italia.it',
                'fiscal_number' => $fiscalNumber,
                'permissions' => [
                    $this->website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'fiscal_number',
                'permissions',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();
        Event::assertDispatched(UserUpdated::class, function ($event) use ($fiscalNumber) {
            return $fiscalNumber === $event->getUser()->fiscal_number;
        });

        $user->deleteAnalyticsServiceAccount();
    }

    /**
     * Test public administration user change role to admin from delegate successful.
     */
    public function testUpdateUserToAdminSuccessful(): void
    {
        $user = factory(User::class)->state('invited')->create([
            'email_verified_at' => Date::now(),
        ]);
        $this->publicAdministration->users()->sync([$user->id], false);
        $user->registerAnalyticsServiceAccount();

        Bouncer::scope()->to($this->publicAdministration->id);
        $user->assign(UserRole::DELEGATED);
        $user->allow(UserPermission::READ_ANALYTICS, $this->website);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->put(route('users.update', ['user' => $user]), [
                '_token' => 'test',
                'email' => $user->email,
                'fiscal_number' => $user->fiscal_number,
                'emailPublicAdministrationUser' => 'new@webanalytics.italia.it',
                'is_admin' => '1',
                'permissions' => [
                    $this->website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertTrue($user->isAn(UserRole::ADMIN));
        Event::assertDispatched(UserWebsiteAccessChanged::class, function ($event) use ($user) {
            return $user->is($event->getUser())
                && $this->website->slug === $event->getWebsite()->slug
                && $event->getAccessType()->is(WebsiteAccessType::WRITE);
        });

        $user->deleteAnalyticsServiceAccount();
    }

    /**
     * Test public administration user change role to delegate from admin successful.
     */
    public function testUpdateUserToDelegateSuccessful(): void
    {
        $user = factory(User::class)->create();
        $this->publicAdministration->users()->sync([$user->id], false);
        $user->registerAnalyticsServiceAccount();

        Bouncer::scope()->to($this->publicAdministration->id);
        $user->assign(UserRole::ADMIN);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->put(route('users.update', ['user' => $user]), [
                '_token' => 'test',
                'email' => $user->email,
                'fiscal_number' => $user->fiscal_number,
                'emailPublicAdministrationUser' => 'new@webanalytics.italia.it',
                'permissions' => [
                    $this->website->id => [
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('users.index'));

        Bouncer::refreshFor($user);
        $this->assertTrue($user->isA(UserRole::DELEGATED));
        Event::assertDispatched(UserWebsiteAccessChanged::class, function ($event) use ($user) {
            return $user->is($event->getUser())
                && $this->website->slug === $event->getWebsite()->slug
                && $event->getAccessType()->is(WebsiteAccessType::VIEW);
        });

        $user->deleteAnalyticsServiceAccount();
    }

    /**
     * Test user update fail due to field validation.
     */
    public function testUpdateUserFailValidation(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->from(route('users.edit', ['user' => $this->user]))
            ->put(route('users.update', ['user' => $this->user]), [
                '_token' => 'test',
                'email' => $this->user->email,
                'permissions' => [
                    $this->website->id => [
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertRedirect(route('users.edit', ['user' => $this->user]))
            ->assertSessionHasErrors([
                'is_admin',
            ]);

        Event::assertNotDispatched(UserWebsiteAccessChanged::class);
    }

    /**
     * Test user suspend successful.
     */
    public function testSuspendUserSuccessful(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.suspend', ['user' => $user]))
            ->assertJson([
                'result' => 'ok',
                'user_name' => e($user->full_name),
                'id' => $user->uuid,
                'status' => UserStatus::getKey(UserStatus::SUSPENDED),
                'status_description' => UserStatus::getDescription(UserStatus::SUSPENDED),
                'administration' => $this->publicAdministration->name,
            ])
            ->assertOk();

        Event::assertDispatched(UserSuspended::class, function ($event) use ($user) {
            return $event->getUser()->id === $user->id;
        });

        Event::assertDispatched(UserUpdated::class, function ($event) {
            $statusPublicAdministrationUser = $event->getUser()->getStatusforPublicAdministration($this->publicAdministration);

            return $statusPublicAdministrationUser->is(UserStatus::SUSPENDED);
        });
    }

    /**
     * Test user suspend fail due to wrong user status.
     */
    public function testSuspendUserFailAlreadySuspended(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::SUSPENDED]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.suspend', ['user' => $user]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserSuspended::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test user suspend fail due to last public administration admin.
     */
    public function testSuspendUserFailLastAdmin(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.suspend', ['user' => $this->user]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Invalid operation for current user',
                'error_code' => 0,
            ]);

        Event::assertNotDispatched(UserSuspended::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test user suspend fail due to user in 'pending' status.
     */
    public function testSuspendUserFailPending(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::PENDING]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.suspend', ['user' => $user]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Invalid operation for current user status',
            ]);

        Event::assertNotDispatched(UserSuspended::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test user suspend fail due to user in 'invited' status.
     */
    public function testSuspendUserFailInvited(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::INVITED,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::INVITED]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.suspend', ['user' => $user]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Invalid operation for current user status',
            ]);

        Event::assertNotDispatched(UserSuspended::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test user reactivation successful.
     */
    public function testReactivateUserSuccessful(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::SUSPENDED]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->json('patch', route('users.reactivate', ['user' => $user]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::ACTIVE),
                'status_description' => UserStatus::getDescription(UserStatus::ACTIVE),
                'administration' => $this->publicAdministration->name,
            ])
            ->assertOk();

        Event::assertDispatched(UserReactivated::class, function ($event) use ($user) {
            return $event->getUser()->id === $user->id;
        });

        Event::assertDispatched(UserUpdated::class, function ($event) {
            $statusPublicAdministrationUser = $event->getUser()->getStatusforPublicAdministration($this->publicAdministration);

            return $statusPublicAdministrationUser->is(UserStatus::ACTIVE);
        });
    }

    /**
     * Test user reactivation fail due to wrong user status.
     */
    public function testReactivateUserFailAlreadyActive(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE]], false);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->json('patch', route('users.reactivate', ['user' => $user]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserReactivated::class);
        Event::assertNotDispatched(UserUpdated::class);
    }
}
