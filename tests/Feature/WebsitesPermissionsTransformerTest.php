<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Websites permissions datatable transformer tests.
 */
class WebsitesPermissionsTransformerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * The website with manageable analytics.
     *
     * @var Website the website
     */
    private $websiteManage;

    /**
     * The website with readable analytics.
     *
     * @var Website the website
     */
    private $websiteRead;

    /**
     * The user.
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

        $this->publicAdministration = factory(PublicAdministration::class)->state('active')->create();

        $this->websiteManage = factory(Website::class)->state('active')->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        do {
            $this->websiteRead = factory(Website::class)->state('active')->make([
                'public_administration_id' => $this->publicAdministration->id,
            ]);
        } while ($this->websiteRead->slug === $this->websiteManage->slug);
        $this->websiteRead->url = 'https://' . $this->websiteRead->url;
        $this->websiteRead->slug = Str::slug($this->websiteRead->url);
        $this->websiteRead->save();

        $this->user = factory(User::class)->state('active')->create();

        $this->publicAdministration->users()->sync($this->user->id);

        Bouncer::dontCache();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->assign(UserRole::DELEGATED);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteManage);
            $this->user->allow(UserPermission::MANAGE_ANALYTICS, $this->websiteManage);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteRead);
        });
    }

    /**
     * Test website permissions as administrator.
     */
    public function testWebsitePermissionAsAdmin(): void
    {
        $userAdmin = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->syncWithoutDetaching($userAdmin->id);
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($userAdmin) {
            $userAdmin->assign(UserRole::ADMIN);
            $userAdmin->allow(UserPermission::MANAGE_USERS);
        });

        $expected = [
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'website_name' => [
                        'display',
                        'raw',
                    ],
                    'type',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'toggles' => [
                        '*' => [
                            'name',
                            'value',
                            'label',
                            'checked',
                            'dataAttributes' => [
                                'entity',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($userAdmin)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('users.websites.permissions.data.json', ['user' => $this->user]))
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteRead->id . '][]', 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteRead->id . '][]', 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteManage->id . '][]', 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteManage->id . '][]', 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonMissing(['icons']);
    }

    /**
     * Test read-only transformer as administrator.
     */
    public function testWebsitePermissionReadOnlyAsAdmin(): void
    {
        $userAdmin = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->syncWithoutDetaching($userAdmin->id);
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($userAdmin) {
            $userAdmin->assign(UserRole::ADMIN);
            $userAdmin->allow(UserPermission::MANAGE_USERS);
        });

        $expected = [
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'website_name' => [
                        'display',
                        'raw',
                    ],
                    'type',
                    'status' => [
                        'display',
                        'raw',
                    ],
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

        $this->actingAs($userAdmin)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('users.websites.permissions.data.json', ['user' => $this->user]) . '?readOnly')
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'website_name' => [
                    'display' => implode('', [
                        '<span>',
                        '<strong>' . e($this->websiteRead->name) . '</strong>',
                        '<br>',
                        '<small><a href="' . $this->websiteRead->url . '" target="_blank">' . e($this->websiteRead->url) . '</a></small>',
                        '</span>',
                    ]),
                    'raw' => e($this->websiteRead->name),
                ],
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
                'website_name' => [
                    'display' => implode('', [
                        '<span>',
                        '<strong>' . e($this->websiteManage->name) . '</strong>',
                        '<br>',
                        '<small><a href="http://' . $this->websiteManage->url . '" target="_blank">' . e($this->websiteManage->url) . '</a></small>',
                        '</span>',
                    ]),
                    'raw' => e($this->websiteManage->name),
                ],
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
    public function testWebsitePermissionAsSuperAdmin(): void
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
                    'website_name' => [
                        'display',
                        'raw',
                    ],
                    'type',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'toggles' => [
                        '*' => [
                            'name',
                            'value',
                            'label',
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
            ->get(route('admin.publicAdministration.users.websites.permissions.data.json', ['publicAdministration' => $this->publicAdministration, 'user' => $this->user]))
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteRead->id . '][]', 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteRead->id . '][]', 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => false,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteManage->id . '][]', 'value' => UserPermission::READ_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonFragment([
                'name' => 'permissions[' . $this->websiteManage->id . '][]', 'value' => UserPermission::MANAGE_ANALYTICS, 'checked' => true,
            ])
            ->assertJsonMissing(['icons']);
    }

    /**
     * Test read-only transformer as super-admin.
     */
    public function testWebsitePermissionReadOnlyAsSuperAdmin(): void
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
                    'website_name' => [
                        'display',
                        'raw',
                    ],
                    'type',
                    'status' => [
                        'display',
                        'raw',
                    ],
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
            ->get(route('admin.publicAdministration.users.websites.permissions.data.json', ['publicAdministration' => $this->publicAdministration, 'user' => $this->user]) . '?readOnly')
            ->assertJsonStructure($expected)
            ->assertJsonFragment([
                'website_name' => [
                    'display' => implode('', [
                        '<span>',
                        '<strong>' . e($this->websiteRead->name) . '</strong>',
                        '<br>',
                        '<small><a href="' . $this->websiteRead->url . '" target="_blank">' . e($this->websiteRead->url) . '</a></small>',
                        '</span>',
                    ]),
                    'raw' => e($this->websiteRead->name),
                ],
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
                'website_name' => [
                    'display' => implode('', [
                        '<span>',
                        '<strong>' . e($this->websiteManage->name) . '</strong>',
                        '<br>',
                        '<small><a href="http://' . $this->websiteManage->url . '" target="_blank">' . e($this->websiteManage->url) . '</a></small>',
                        '</span>',
                    ]),
                    'raw' => e($this->websiteManage->name),
                ],
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
