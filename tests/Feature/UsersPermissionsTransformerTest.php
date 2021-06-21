<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Users permissions datatable transformer tests.
 */
class UsersPermissionsTransformerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public administration.
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
     * The public administration administrator.
     *
     * @var User the user
     */
    private $userAdmin;

    /**
     * The public administration analytics manager.
     *
     * @var User the user
     */
    private $userManager;

    /**
     * The public administration analytics reader.
     *
     * @var User the user
     */
    private $userReader;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicAdministration = factory(PublicAdministration::class)->state('active')->create();

        $this->website = factory(Website::class)->state('active')->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $this->userAdmin = factory(User::class)->state('active')->create();
        $this->userManager = factory(User::class)->state('active')->create();
        $this->userReader = factory(User::class)->state('active')->create();

        $this->publicAdministration->users()->sync([$this->userAdmin->id, $this->userManager->id, $this->userReader->id]);

        Bouncer::dontCache();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->userAdmin->assign(UserRole::ADMIN);
            $this->userAdmin->allow(UserPermission::MANAGE_WEBSITES);
            $this->userAdmin->allow(UserPermission::MANAGE_ANALYTICS, $this->website);
            $this->userAdmin->allow(UserPermission::READ_ANALYTICS, $this->website);

            $this->userManager->assign(UserRole::DELEGATED);
            $this->userManager->allow(UserPermission::READ_ANALYTICS, $this->website);
            $this->userManager->allow(UserPermission::MANAGE_ANALYTICS, $this->website);

            $this->userReader->assign(UserRole::DELEGATED);
            $this->userReader->allow(UserPermission::READ_ANALYTICS, $this->website);
        });
    }

    /**
     * Test transformer as administrator.
     */
    public function testUserPermissionsTransformerAsAdmin(): void
    {
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
                    'status',
                    'toggles' => [
                        '*' => [
                            'name',
                            'value',
                            'label',
                            'disabled',
                            'checked',
                            'dataAttributes' => [
                                'entity',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($this->userAdmin)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('websites.users.permissions.data.json', ['website' => $this->website]))
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userReader->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userReader->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userManager->id . '][]', 'disabled' => false, 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userManager->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userAdmin->id . '][]', 'disabled' => true, 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userAdmin->id . '][]', 'disabled' => true, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonMissing(['icons']);
    }

    /**
     * Test read-only transformer as administrator.
     */
    public function testUserPermissionsTransformerReadOnlyAsAdmin(): void
    {
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
                    'status',
                    'icons' => [
                        '*' => [
                            'icon',
                            'color',
                            'label',
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($this->userAdmin)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('websites.users.permissions.data.json', ['website' => $this->website]) . '?readOnly')
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'email' => e($this->userReader->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'danger',
                        'icon' => 'it-close-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => e($this->userManager->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => e($this->userAdmin->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonMissing(['toggles']);
    }

    /**
     * Test transformer as super-admin.
     */
    public function testUserPermissionsTransformerAsSuperAdmin(): void
    {
        $superAdmin = factory(User::class)->state('active')->create();
        Bouncer::scope()->onceTo(0, function () use ($superAdmin) {
            $superAdmin->assign(UserRole::SUPER_ADMIN);
            $superAdmin->allow(UserPermission::ACCESS_ADMIN_AREA);
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
                    'status',
                    'toggles' => [
                        '*' => [
                            'name',
                            'value',
                            'label',
                            'disabled',
                            'checked',
                            'dataAttributes' => [
                                'entity',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($superAdmin)
            ->get(route('admin.publicAdministration.websites.users.permissions.data.json', ['publicAdministration' => $this->publicAdministration, 'website' => $this->website]))
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userReader->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userReader->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userManager->id . '][]', 'disabled' => false, 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userManager->id . '][]', 'disabled' => false, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userAdmin->id . '][]', 'disabled' => true, 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->userAdmin->id . '][]', 'disabled' => true, 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonMissing(['icons']);
    }

    /**
     * Test read-only transformer as super-admin.
     */
    public function testUserPermissionsTransformerReadOnlyAsSuperAdmin(): void
    {
        $superAdmin = factory(User::class)->state('active')->create();
        Bouncer::scope()->onceTo(0, function () use ($superAdmin) {
            $superAdmin->assign(UserRole::SUPER_ADMIN);
            $superAdmin->allow(UserPermission::ACCESS_ADMIN_AREA);
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
                    'status',
                    'icons' => [
                        '*' => [
                            'icon',
                            'color',
                            'label',
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($superAdmin)
            ->get(route('admin.publicAdministration.websites.users.permissions.data.json', ['publicAdministration' => $this->publicAdministration, 'website' => $this->website]) . '?readOnly')
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'email' => e($this->userReader->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'danger',
                        'icon' => 'it-close-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => e($this->userManager->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => e($this->userAdmin->email),
                'icons' => [
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::READ_ANALYTICS),
                    ],
                    [
                        'color' => 'success',
                        'icon' => 'it-check-circle',
                        'label' => UserPermission::getDescription(UserPermission::MANAGE_ANALYTICS),
                    ],
                ],
            ])
            ->assertJsonMissing(['toggles']);
    }
}
