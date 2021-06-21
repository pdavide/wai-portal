<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Websites transformer tests.
 */
class WebsiteTransformerTest extends TestCase
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
     * The primary website.
     *
     * @var Website the website
     */
    private $websitePrimary;

    /**
     * The secondary active website.
     *
     * @var Website the website
     */
    private $websiteSecondaryActive;

    /**
     * The secondary archived website.
     *
     * @var Website the website
     */
    private $websiteSecondaryArchived;

    /**
     * The secondary pending website.
     *
     * @var Website the website
     */
    private $websiteSecondaryPending;

    /**
     * The secondary deleted website.
     *
     * @var Website the website
     */
    private $websiteSecondaryTrashed;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->state('active')->create();
        $this->publicAdministration = factory(PublicAdministration::class)->state('active')->create();
        $this->publicAdministration->users()->sync($this->user->id);

        $this->websitePrimary = factory(Website::class)->state('active')->create([
            'url' => 'https://primary.local',
            'slug' => Str::slug('https://primary.local'),
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 1,
        ]);

        $this->websiteSecondaryActive = factory(Website::class)->state('active')->create([
            'type' => WebsiteType::INFORMATIONAL,
            'url' => 'https://seconday-active.local',
            'slug' => Str::slug('https://seconday-active.local'),
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 2,
        ]);

        $this->websiteSecondaryArchived = factory(Website::class)->state('archived')->create([
            'type' => WebsiteType::INFORMATIONAL,
            'url' => 'https://seconday-archived.local',
            'slug' => Str::slug('https://seconday-archived.local'),
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 3,
        ]);

        $this->websiteSecondaryPending = factory(Website::class)->create([
            'type' => WebsiteType::INFORMATIONAL,
            'status' => WebsiteStatus::PENDING,
            'url' => 'https://seconday-pending.local',
            'slug' => Str::slug('https://seconday-pending.local'),
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 4,
        ]);

        $this->websiteSecondaryTrashed = factory(Website::class)->state('active')->create([
            'type' => WebsiteType::INFORMATIONAL,
            'url' => 'https://seconday-trashed.local',
            'slug' => Str::slug('https://seconday-trashed.local'),
            'public_administration_id' => $this->publicAdministration->id,
            'deleted_at' => now(),
            'analytics_id' => 5,
        ]);

        Bouncer::dontCache();
    }

    /**
     * Test transformer as delegate.
     */
    public function testWebsiteTransformAsDelegate(): void
    {
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->assign(UserRole::DELEGATED);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteSecondaryActive);
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
                    'added_at',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'icons',
                    'buttons',
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('websites.data.json'))
            ->assertJsonStructure($expected)
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.show', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryTrashed,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryTrashed,
            ])])
            ->assertJsonFragment(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryActive->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websitePrimary->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryArchived->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryPending->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryTrashed->analytics_id])]);
    }

    /**
     * Test transformer as administrator.
     */
    public function testWebsiteTransformAsAdmin(): void
    {
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->assign(UserRole::ADMIN);
            $this->user->allow(UserPermission::MANAGE_WEBSITES);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websitePrimary);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteSecondaryActive);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteSecondaryArchived);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteSecondaryPending);
            $this->user->allow(UserPermission::READ_ANALYTICS, $this->websiteSecondaryTrashed);
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
                    'added_at',
                    'status' => [
                        'display',
                        'raw',
                    ],
                    'icons',
                    'buttons',
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
            ])
            ->get(route('websites.data.json'))
            ->assertJsonStructure($expected)
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('websites.show', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.show', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.tracking.check', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonFragment(['link' => route('websites.edit', ['website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('websites.edit', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonFragment(['link' => route('websites.edit', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('websites.edit', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.edit', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('websites.archive', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('websites.archive', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('websites.unarchive', ['website' => $this->websiteSecondaryTrashed])])
            ->assertJsonFragment(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websitePrimary->analytics_id])])
            ->assertJsonFragment(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryActive->analytics_id])])
            ->assertJsonFragment(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryArchived->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryPending->analytics_id])])
            ->assertJsonMissing(['link' => route('analytics.service.login', ['websiteAnalyticsId' => $this->websiteSecondaryTrashed->analytics_id])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryTrashed,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryTrashed,
            ])]);
    }

    /**
     * Test transformer as super-admin.
     */
    public function testWebsiteTransformAsSuperAdmin(): void
    {
        $superAdmin = factory(User::class)->state('active')->create();

        Bouncer::scope()->onceTo(0, function () use ($superAdmin) {
            $superAdmin->assign(UserRole::SUPER_ADMIN);
            $superAdmin->allow(UserPermission::ACCESS_ADMIN_AREA);
            $superAdmin->allow(UserPermission::MANAGE_WEBSITES);
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
                    'added_at',
                    'status', //NOTE: trashed websites don't have status
                    'icons',
                    'buttons',
                ],
            ],
        ];

        $this->actingAs($superAdmin)
            ->get(route('admin.publicAdministration.websites.data.json', ['publicAdministration' => $this->publicAdministration]))
            ->assertJsonStructure($expected)
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.show', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.show', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryActive])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.show', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.show', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.show', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.tracking.check', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.tracking.check', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.tracking.check', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.tracking.check', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.tracking.check', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryTrashed])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.edit', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.edit', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryActive])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.edit', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.edit', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.edit', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.archive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websitePrimary])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.unarchive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websitePrimary])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.archive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.unarchive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryActive])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.archive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryArchived])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.unarchive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryArchived])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.archive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.unarchive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryPending])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.archive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.unarchive', ['publicAdministration' => $this->publicAdministration, 'website' => $this->websiteSecondaryTrashed])])
            ->assertJsonMissing(['link' => route('analytics.service.login')])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websitePrimary,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryActive,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryArchived,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryPending,
            ])])
            ->assertJsonFragment(['link' => route('admin.publicAdministration.websites.restore', [
                'publicAdministration' => $this->publicAdministration,
                'trashed_website' => $this->websiteSecondaryTrashed,
            ])])
            ->assertJsonMissing(['link' => route('admin.publicAdministration.websites.delete', [
                'publicAdministration' => $this->publicAdministration,
                'website' => $this->websiteSecondaryTrashed,
            ])]);
    }
}
