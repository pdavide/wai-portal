<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Events\User\UserDeleted;
use App\Events\User\UserEmailForPublicAdministrationChanged;
use App\Events\User\UserInvited;
use App\Events\User\UserReactivated;
use App\Events\User\UserSuspended;
use App\Events\User\UserUpdated;
use App\Events\User\UserWebsiteAccessChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Super admin user CRUD test.
 */
class CRUDAdminUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The super admin user.
     *
     * @var User the user
     */
    private $user;

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

        Bouncer::dontCache();
        Bouncer::scope()->onceTo(0, function () {
            $this->user->assign(UserRole::SUPER_ADMIN);
            $this->user->allow(UserPermission::MANAGE_USERS);
            $this->user->allow(UserPermission::ACCESS_ADMIN_AREA);
        });
    }

    /**
     * Test JSON data for datatable successful.
     */
    public function testDatatableData(): void
    {
        $this->actingAs($this->user)
            ->json('GET', route('admin.users.data.json'))
            ->assertOk()
            ->assertJsonFragment([
                'raw' => e($this->user->full_name),
            ]);
    }

    /**
     * Test super admin creation successful.
     */
    public function testCreateSuperAdminUserSuccessful(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.users.store'), [
                '_token' => 'test',
                'email' => 'new@webanalytics.italia.it',
                'name' => 'Mario',
                'family_name' => 'Rossi',
            ])
            ->assertSessionDoesntHaveErrors([
                'name',
                'family_name',
                'email',
            ])
            ->assertRedirect(route('admin.users.index'));

        Event::assertDispatched(UserInvited::class, function ($event) {
            return
                'new@webanalytics.italia.it' === $event->getUser()->email
                && null === $event->getPublicAdministration()
                && $this->user->is($event->getInvitedBy());
        });
    }

    /**
     * Test super admin creation fails due to fields validation.
     */
    public function testCreateSuperAdminUserFailValidation(): void
    {
        $this->actingAs($this->user)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                '_token' => 'test',
                'email' => $this->user->email,
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors([
                'email',
                'name',
                'family_name',
            ]);

        Event::assertNotDispatched(UserInvited::class);
    }

    /**
     * Test super admin update successful.
     */
    public function testUpdateSuperAdminUserSuccessful(): void
    {
        $user = factory(User::class)->state('invited')->create([
            'email_verified_at' => Date::now(),
        ]);

        Bouncer::scope()->onceTo(0, function () use ($user) {
            $user->assign(UserRole::SUPER_ADMIN);
            $user->allow(UserPermission::ACCESS_ADMIN_AREA);
            $user->allow(UserPermission::MANAGE_USERS);
        });

        $this->actingAs($this->user)
            ->put(route('admin.users.update', ['user' => $user]), [
                '_token' => 'test',
                'email' => 'new@webanalytics.italia.it',
                'name' => 'Mario',
                'family_name' => 'Rossi',
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
            ])
            ->assertRedirect(route('admin.users.index'));

        Event::assertDispatched(UserUpdated::class, function ($event) {
            $user = $event->getUser();

            return 'new@webanalytics.italia.it' === $user->email
                && 'Mario' === $user->name
                && 'Rossi' === $user->family_name;
        });
    }

    /**
     * Test super admin update fail due to field validation.
     */
    public function testUpdateSuperAdminUserFailValidation(): void
    {
        $this->actingAs($this->user)
            ->from(route('admin.users.edit', ['user' => $this->user]))
            ->put(route('admin.users.update', ['user' => $this->user]), [
                '_token' => 'test',
            ])
            ->assertRedirect(route('admin.users.edit', ['user' => $this->user]))
            ->assertSessionHasErrors([
                'name',
                'family_name',
                'email',
            ]);

        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test super admin suspend successful.
     */
    public function testSuspendSuperAdminUserSuccessful(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        Bouncer::scope()->onceTo(0, function () use ($user) {
            $user->assign(UserRole::SUPER_ADMIN);
            $user->allow(UserPermission::ACCESS_ADMIN_AREA);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.users.suspend', ['user' => $user]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::SUSPENDED),
                'status_description' => UserStatus::getDescription(UserStatus::SUSPENDED),
                'trashed' => $user->trashed(),
                'administration' => null,
            ])
            ->assertOk();

        Event::assertDispatched(UserSuspended::class, function ($event) use ($user) {
            return $event->getUser()->id === $user->id;
        });

        Event::assertDispatched(UserUpdated::class, function ($event) {
            return $event->getUser()->status->is(UserStatus::SUSPENDED);
        });
    }

    /**
     * Test super admin suspend fail due to wrong status.
     */
    public function testSuspendSuperAdminUserFailAlreadySuspended(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::SUSPENDED,
        ]);
        Bouncer::scope()->onceTo(0, function () use ($user) {
            $user->assign(UserRole::SUPER_ADMIN);
            $user->allow(UserPermission::ACCESS_ADMIN_AREA);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.users.suspend', ['user' => $user]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserSuspended::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test super admin suspend fail due to last super admin.
     */
    public function testSuspendSuperAdminUserFailLastAdmin(): void
    {
        $this->actingAs($this->user)
            ->json('patch', route('admin.users.suspend', ['user' => $this->user]))
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
     * Test super admin reactivation successful.
     */
    public function testReactivateSuperAdminUserSuccessful(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::SUSPENDED,
        ]);

        $this->actingAs($this->user)
            ->json('patch', route('admin.users.reactivate', ['user' => $user]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::INVITED),
                'status_description' => UserStatus::getDescription(UserStatus::INVITED),
            ])
            ->assertOk();

        Event::assertDispatched(UserReactivated::class, function ($event) use ($user) {
            return $event->getUser()->id === $user->id;
        });

        Event::assertDispatched(UserUpdated::class, function ($event) {
            return $event->getUser()->status->is(UserStatus::INVITED);
        });
    }

    /**
     * Test super admin reactivation fail due to wrong status.
     */
    public function testReactivateSuperAdminUserFailAlreadyActive(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($this->user)
            ->json('patch', route('admin.users.reactivate', ['user' => $user]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserReactivated::class);
        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test JSON data for public administration users datatable successful.
     */
    public function testPublicAdministrationUserDatatableData(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        Bouncer::scope()->onceTo(0, function () use ($user) {
            $user->assign(UserRole::ADMIN);
            $user->allow(UserPermission::MANAGE_ANALYTICS);
        });

        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_email' => $user->email, 'user_status' => UserStatus::ACTIVE]]);

        $this->actingAs($this->user)
            ->json('GET', route('admin.publicAdministration.users.data.json', ['publicAdministration' => $publicAdministration]))
            ->assertOk()
            ->assertJsonFragment([
                'raw' => e($user->full_name),
            ]);
    }

    /**
     * Test public administration admin creation successful.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    public function testPublicAdministrationCreateAdminUserSuccessful(): void
    {
        $email = 'new@webanalytics.italia.it';
        $fiscalNumber = 'ESXLKY44P09I168D';
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.publicAdministration.users.store', ['publicAdministration' => $publicAdministration]), [
                '_token' => 'test',
                'email' => $email,
                'fiscal_number' => $fiscalNumber,
                'is_admin' => '1',
                'permissions' => [
                    $website->id => [
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
            ->assertRedirect(route('admin.publicAdministration.users.index', ['publicAdministration' => $publicAdministration]));

        Event::assertDispatched(UserInvited::class, function ($event) use ($email, $publicAdministration) {
            return
                $email === $event->getUser()->email
                && $publicAdministration->ipa_code === $event->getPublicAdministration()->ipa_code
                && $this->user->is($event->getInvitedBy());
        });

        User::findNotSuperAdminByFiscalNumber($fiscalNumber)->deleteAnalyticsServiceAccount();
    }

    /**
     * Test public administration user creation successful.
     *
     * @throws \App\Exceptions\AnalyticsServiceException if unable to connect the Analytics Service
     * @throws \App\Exceptions\CommandErrorException if command is unsuccessful
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to the service
     */
    public function testPublicAdministrationCreateUserSuccessful(): void
    {
        $email = 'new@webanalytics.italia.it';
        $fiscalNumber = 'ESXLKY44P09I168D';
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.publicAdministration.users.store', ['publicAdministration' => $publicAdministration]), [
                '_token' => 'test',
                'email' => $email,
                'fiscal_number' => $fiscalNumber,
                'permissions' => [
                    $website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'fiscal_number',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('admin.publicAdministration.users.index', ['publicAdministration' => $publicAdministration]));

        Event::assertDispatched(UserInvited::class, function ($event) use ($email, $publicAdministration) {
            return
                $email === $event->getUser()->email
                && $publicAdministration->ipa_code === $event->getPublicAdministration()->ipa_code
                && $this->user->is($event->getInvitedBy());
        });

        User::findNotSuperAdminByFiscalNumber($fiscalNumber)->deleteAnalyticsServiceAccount();
    }

    /**
     * Test user creation fails due to fields validation.
     */
    public function testPublicAdministrationCreateUserFailValidation(): void
    {
        $user = factory(User::class)->create();
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id]);

        $fiscalNumber = 'XNTMDF63C44D878E';

        $this->actingAs($this->user)
            ->from(route('admin.publicAdministration.users.create', ['publicAdministration' => $publicAdministration]))
            ->post(route('admin.publicAdministration.users.store', ['publicAdministration' => $publicAdministration]), [
                '_token' => 'test',
                'email' => $user->email,
                'fiscal_number' => $fiscalNumber,
            ])
            ->assertRedirect(route('admin.publicAdministration.users.create', ['publicAdministration' => $publicAdministration]))
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
    public function testPublicAdministrationUpdateUserEmailSuccessful(): void
    {
        $user = factory(User::class)->state('invited')->create();
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE, 'user_email' => 'old@webanalytics.italia.it']]);
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        $analyticsId = app()->make('analytics-service')->registerSite($website->name . ' [' . $website->type . ']', $website->url, $publicAdministration->name);
        $website->analytics_id = $analyticsId;
        $website->save();
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user, $website) {
            $user->assign(UserRole::ADMIN);
            $user->registerAnalyticsServiceAccount();
            Bouncer::allow($user)->to(UserPermission::READ_ANALYTICS, $website);
            Bouncer::allow($user)->to(UserPermission::MANAGE_ANALYTICS, $website);
            Bouncer::disallow($user)->to(UserPermission::NO_ACCESS, $website);
        });
        Bouncer::refreshFor($user);
        app()->make('analytics-service')->setWebsiteAccess($user->uuid, WebsiteAccessType::WRITE, $website->analytics_id);

        $this->actingAs($this->user)
            ->put(route('admin.publicAdministration.users.update', [
                    'publicAdministration' => $publicAdministration,
                    'user' => $user,
            ]), [
                '_token' => 'test',
                'email' => 'new@webanalytics.italia.it',
                'emailPublicAdministrationUser' => 'updated@webanalytics.italia.it',
                'fiscal_number' => $user->fiscal_number,
                'is_admin' => '1',
                'permissions' => [
                    $website->id => [
                        UserPermission::MANAGE_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->user->refresh();
        Event::assertDispatched(UserEmailForPublicAdministrationChanged::class, function ($event) {
            $publicAdministration = $event->getPublicAdministration();
            $user = $event->getUser();
            $emailPublicAdministrationUser = $user->getEmailForPublicAdministration($publicAdministration);

            return 'updated@webanalytics.italia.it' === $emailPublicAdministrationUser;
        });

        $user->deleteAnalyticsServiceAccount();
        app()->make('analytics-service')->deleteSite($website->analytics_id);
    }

    /**
     * Test public administration user change role to admin from delegate successful.
     */
    public function testPublicAdministrationUpdateUserToAdminSuccessful(): void
    {
        $user = factory(User::class)->state('invited')->create([
            'email_verified_at' => Date::now(),
        ]);
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE, 'user_email' => 'old@webanalytics.italia.it']]);
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        $user->registerAnalyticsServiceAccount();

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user, $website) {
            $user->assign(UserRole::DELEGATED);
            $user->allow(UserPermission::READ_ANALYTICS, $website);
        });

        $this->actingAs($this->user)
            ->put(route('admin.publicAdministration.users.update', [
                    'publicAdministration' => $publicAdministration,
                    'user' => $user,
            ]), [
                '_token' => 'test',
                'email' => $user->email,
                'emailPublicAdministrationUser' => 'old@webanalytics.italia.it',
                'fiscal_number' => $user->fiscal_number,
                'is_admin' => '1',
                'permissions' => [
                    $website->id => [
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
            ->assertRedirect(route('admin.publicAdministration.users.index', ['publicAdministration' => $publicAdministration]));

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $this->assertTrue($user->isAn(UserRole::ADMIN));
        });
        Event::assertDispatched(UserWebsiteAccessChanged::class, function ($event) use ($user, $website) {
            return $user->is($event->getUser())
                && $website->slug === $event->getWebsite()->slug
                && $event->getAccessType()->is(WebsiteAccessType::WRITE);
        });

        $user->deleteAnalyticsServiceAccount();
    }

    /**
     * Test public administration user change role to delegate from admin successful.
     */
    public function testPublicAdministrationUpdateUserToDelegateSuccessful(): void
    {
        $user = factory(User::class)->create();
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE, 'user_email' => 'old@webanalytics.italia.it']]);
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        $user->registerAnalyticsServiceAccount();

        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::ADMIN);
        });

        $this->actingAs($this->user)
            ->put(route('admin.publicAdministration.users.update', [
                    'publicAdministration' => $publicAdministration,
                    'user' => $user,
            ]), [
                '_token' => 'test',
                'email' => $user->email,
                'emailPublicAdministrationUser' => 'old@webanalytics.italia.it',
                'permissions' => [
                    $website->id => [
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'email',
                'is_admin',
                'permissions',
            ])
            ->assertRedirect(route('admin.publicAdministration.users.index', ['publicAdministration' => $publicAdministration]));

        Bouncer::refreshFor($user);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $this->assertTrue($user->isA(UserRole::DELEGATED));
        });
        Event::assertDispatched(UserWebsiteAccessChanged::class, function ($event) use ($user, $website) {
            return $user->is($event->getUser())
                && $website->slug === $event->getWebsite()->slug
                && $event->getAccessType()->is(WebsiteAccessType::VIEW);
        });

        $user->deleteAnalyticsServiceAccount();
    }

    /**
     * Test user update fail due to field validation.
     */
    public function testPublicAdministrationUpdateUserFailValidation(): void
    {
        $user = factory(User::class)->state('active')->create();
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id], false);
        $website = factory(Website::class)->create([
            'public_administration_id' => $publicAdministration->id,
        ]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::ADMIN);
            $user->allow(UserPermission::MANAGE_USERS);
        });

        $this->actingAs($this->user)
            ->from(route('admin.publicAdministration.users.edit', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->put(route('admin.publicAdministration.users.update', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]), [
                '_token' => 'test',
                'email' => $user->email,
                'permissions' => [
                    $website->id => [
                        UserPermission::READ_ANALYTICS,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.publicAdministration.users.edit', ['publicAdministration' => $publicAdministration, 'user' => $user]))
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
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id], false);

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.suspend', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::SUSPENDED),
                'status_description' => UserStatus::getDescription(UserStatus::SUSPENDED),
                'administration' => $publicAdministration->name,
            ])
            ->assertOk();

        Event::assertDispatched(UserUpdated::class, function ($event) use ($publicAdministration) {
            $statusPublicAdministrationUser = $event->getUser()->getStatusforPublicAdministration($publicAdministration);

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
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::SUSPENDED]], false);

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.suspend', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test user suspend fail due to last public administration admin.
     */
    public function testSuspendUserSuccessfulEvenLastAdmin(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE]], false);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::ADMIN);
            $user->allow(UserPermission::MANAGE_USERS);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.suspend', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::SUSPENDED),
                'status_description' => UserStatus::getDescription(UserStatus::SUSPENDED),
                'administration' => $publicAdministration->name,
            ])
            ->assertOk();

        Event::assertDispatched(UserUpdated::class, function ($event) use ($publicAdministration) {
            $statusPublicAdministrationUser = $event->getUser()->getStatusforPublicAdministration($publicAdministration);

            return $statusPublicAdministrationUser->is(UserStatus::SUSPENDED);
        });
    }

    /**
     * Test user suspend fail due to user in 'pending' status.
     */
    public function testSuspendUserFailPending(): void
    {
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $publicAdministration = factory(PublicAdministration::class)->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::PENDING]], false);

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.suspend', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Invalid operation for current user status',
            ]);

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
        $publicAdministration = factory(PublicAdministration::class)->create();
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::SUSPENDED]], false);

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.reactivate', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertJson([
                'result' => 'ok',
                'id' => $user->uuid,
                'user_name' => e($user->full_name),
                'status' => UserStatus::getKey(UserStatus::ACTIVE),
                'status_description' => UserStatus::getDescription(UserStatus::ACTIVE),
                'administration' => $publicAdministration->name,
            ])
            ->assertOk();

        Event::assertDispatched(UserUpdated::class, function ($event) use ($publicAdministration) {
            $statusPublicAdministrationUser = $event->getUser()->getStatusforPublicAdministration($publicAdministration);

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
        $publicAdministration = factory(PublicAdministration::class)->create();
        $publicAdministration->users()->sync([$user->id], false);

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.reactivate', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertStatus(303);

        Event::assertNotDispatched(UserUpdated::class);
    }

    /**
     * Test normal user removal successful.
     */
    public function testPublicAdministrationDeleteUserSuccessful(): void
    {
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE]]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::DELEGATED);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertOk();

        $this->assertTrue($user->publicAdministrations->where('id', $publicAdministration->id)->isEmpty());

        Event::assertDispatched(UserDeleted::class, function ($event) use ($user) {
            return $user->is($event->getUser());
        });
    }

    /**
     * Test normal user removal fail due to last public administration admin.
     */
    public function testPublicAdministrationDeleteUserFailLastAdmin(): void
    {
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::ACTIVE]]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::ADMIN);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'The last administrator cannot be removed or suspended',
                'error_code' => '0',
            ]);

        Event::assertNotDispatched(UserDeleted::class);
    }

    /**
     * Test normal user removal fail due to user in 'pending' status.
     */
    public function testPublicAdministrationDeleteUserFailPending(): void
    {
        $publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $user = factory(User::class)->create([
            'status' => UserStatus::PENDING,
        ]);
        $publicAdministration->users()->sync([$user->id => ['user_status' => UserStatus::PENDING]]);
        Bouncer::scope()->onceTo($publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::DELEGATED);
        });

        $this->actingAs($this->user)
            ->json('patch', route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $publicAdministration,
                'user' => $user,
            ]))
            ->assertStatus(400)
            ->assertJson([
                'result' => 'error',
                'message' => 'Pending users cannot be deleted',
            ]);

        Event::assertNotDispatched(UserDeleted::class);
    }
}
