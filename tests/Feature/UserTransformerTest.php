<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Users transformer tests.
 */
class UserTransformerTest extends TestCase
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
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->state('active')->create();
        $this->publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $this->publicAdministration->users()->sync($this->user->id);

        Bouncer::dontCache();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->assign(UserRole::ADMIN);
            $this->user->allow(UserPermission::MANAGE_USERS);
        });
    }

    /**
     * Test transformer as delegate.
     */
    public function testUserTransformTestAsDelegate(): void
    {
        $user = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->syncWithoutDetaching($user->id);
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($user) {
            $user->assign(UserRole::DELEGATED);
        });

        $expected = [
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'name' => [
                        'display',
                        'raw',
                    ],
                    'email',
                    'added_at',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'buttons',
                    'icons',
                ],
            ],
        ];

        $this->actingAs($user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('users.data.json'))
            ->assertJsonStructure($expected)
            ->assertJsonMissing(['link' => route('users.show', ['user' => $this->user])])
            ->assertJsonMissing(['link' => route('users.edit', ['user' => $this->user])])
            ->assertJsonMissing(['link' => route('users.reactivate', ['user' => $this->user])])
            ->assertJsonMissing(['link' => route('users.suspend', ['user' => $this->user])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_user' => $this->user,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $this->user,
            ])]);
    }

    /**
     * Test transformer as administrator.
     */
    public function testUserTransformTestAsAdmin(): void
    {
        $userInvited = factory(User::class)->state('active')->create();
        $userActive = factory(User::class)->state('active')->create();
        $userSuspended = factory(User::class)->state('active')->create();

        $this->publicAdministration->users()->syncWithoutDetaching([
            $userInvited->id => ['user_status' => UserStatus::INVITED],
            $userActive->id => ['user_status' => UserStatus::ACTIVE],
            $userSuspended->id => ['user_status' => UserStatus::SUSPENDED],
        ]);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($userInvited, $userActive, $userSuspended) {
            $userInvited->assign(UserRole::DELEGATED);
            $userActive->assign(UserRole::DELEGATED);
            $userSuspended->assign(UserRole::DELEGATED);
        });

        $expected = [
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'name' => [
                        'display',
                        'raw',
                    ],
                    'email',
                    'added_at',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'buttons',
                    'icons',
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('users.data.json'))
            ->assertJsonStructure($expected)
            ->assertJsonMissing(['link' => route('users.suspend', ['user' => $userInvited])])
            ->assertJsonFragment(['link' => route('users.suspend', ['user' => $userActive])])
            ->assertJsonMissing(['link' => route('users.suspend', ['user' => $userSuspended])])
            ->assertJsonMissing(['link' => route('users.reactivate', ['user' => $userInvited])])
            ->assertJsonMissing(['link' => route('users.reactivate', ['user' => $userActive])])
            ->assertJsonFragment(['link' => route('users.reactivate', ['user' => $userSuspended])])
            ->assertJsonFragment(['link' => route('users.show', ['user' => $userInvited])])
            ->assertJsonFragment(['link' => route('users.show', ['user' => $userActive])])
            ->assertJsonFragment(['link' => route('users.show', ['user' => $userSuspended])])
            ->assertJsonFragment(['link' => route('users.edit', ['user' => $userInvited])])
            ->assertJsonFragment(['link' => route('users.edit', ['user' => $userActive])])
            ->assertJsonFragment(['link' => route('users.edit', ['user' => $userSuspended])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userInvited,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userSuspended,
            ])]);
    }

    /**
     * Test transformer as super-admin.
     */
    public function testUserTransformAsSuperAdmin(): void
    {
        $superAdmin = factory(User::class)->state('active')->create();

        Bouncer::scope()->onceTo(0, function () use ($superAdmin) {
            $superAdmin->assign(UserRole::SUPER_ADMIN);
            $superAdmin->allow(UserPermission::ACCESS_ADMIN_AREA);
        });

        $userPending = factory(User::class)->state('pending')->create();
        $userInvited = factory(User::class)->state('invited')->create();
        $userActive = factory(User::class)->state('active')->create();
        $userSuspended = factory(User::class)->state('active')->create();

        $this->publicAdministration->users()->syncWithoutDetaching([
            $userInvited->id => ['user_status' => UserStatus::INVITED],
            $userActive->id => ['user_status' => UserStatus::ACTIVE],
            $userSuspended->id => ['user_status' => UserStatus::SUSPENDED],
        ]);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($userPending, $userInvited, $userActive, $userSuspended) {
            $userPending->assign(UserRole::DELEGATED);
            $userInvited->assign(UserRole::DELEGATED);
            $userActive->assign(UserRole::ADMIN);
            $userSuspended->assign(UserRole::DELEGATED);
        });

        $expected = [
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'name' => [
                        'display',
                        'raw',
                    ],
                    'email',
                    'added_at',
                    'status',
                    'buttons',
                    'icons',
                ],
            ],
        ];

        $this->actingAs($superAdmin)
            ->get(route('admin.publicAdministration.users.data.json', ['publicAdministration' => $this->publicAdministration]))
            ->assertJsonStructure($expected)

            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.show', ['publicAdministration' => $this->publicAdministration, 'user' => $userInvited])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.show', ['publicAdministration' => $this->publicAdministration, 'user' => $userActive])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.show', ['publicAdministration' => $this->publicAdministration, 'user' => $userSuspended])])

            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.edit', ['publicAdministration' => $this->publicAdministration, 'user' => $userInvited])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.edit', ['publicAdministration' => $this->publicAdministration, 'user' => $userActive])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.edit', ['publicAdministration' => $this->publicAdministration, 'user' => $userSuspended])])

            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.suspend', ['publicAdministration' => $this->publicAdministration, 'user' => $userPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.reactivate', ['publicAdministration' => $this->publicAdministration, 'user' => $userPending])])

            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.suspend', ['publicAdministration' => $this->publicAdministration, 'user' => $userInvited])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.reactivate', ['publicAdministration' => $this->publicAdministration, 'user' => $userInvited])])

            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.suspend', ['publicAdministration' => $this->publicAdministration, 'user' => $userActive])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.reactivate', ['publicAdministration' => $this->publicAdministration, 'user' => $userActive])])

            ->assertJsonMissing(['link' => route('admin.publicAdministration.users.suspend', ['publicAdministration' => $this->publicAdministration, 'user' => $userSuspended])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.reactivate', ['publicAdministration' => $this->publicAdministration, 'user' => $userSuspended])])

            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userInvited,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userActive,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.users.delete', [
                'publicAdministration' => $this->publicAdministration,
                'user' => $userSuspended,
            ])]);
    }
}
