<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Events\Website\PrimaryWebsiteNotTracking;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteArchiving;
use App\Events\Website\WebsiteDeleted;
use App\Events\Website\WebsitePurged;
use App\Events\Website\WebsitePurging;
use App\Events\Website\WebsiteRestored;
use App\Events\Website\WebsiteStatusChanged;
use App\Events\Website\WebsiteUnarchived;
use App\Events\Website\WebsiteUpdated;
use App\Events\Website\WebsiteUrlChanged;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Notifications\RTDWebsiteActivatedEmail;
use App\Notifications\UserPrimaryWebsiteNotTrackingEmail;
use App\Notifications\UserWebsiteActivatedEmail;
use App\Notifications\UserWebsiteAddedEmail;
use App\Notifications\UserWebsiteArchivedEmail;
use App\Notifications\UserWebsiteArchivingEmail;
use App\Notifications\UserWebsitePurgedEmail;
use App\Notifications\UserWebsitePurgingEmail;
use App\Notifications\UserWebsiteUnarchivedEmail;
use App\Notifications\UserWebsiteUrlChangedEmail;
use App\Notifications\WebsiteAddedEmail;
use App\Services\MatomoService;
use App\Traits\HasAnalyticsDashboard;
use App\Traits\InteractsWithRedisIndex;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Websites events listener tests.
 */
class WebsiteEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public administration the website belongs to.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * The activated website.
     *
     * @var Website the website
     */
    private $website;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

    /**
     * Fake data generator.
     *
     * @var Generator the generator
     */
    private $faker;

    /**
     * Pre-tests setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Factory::create();
        $this->publicAdministration = factory(PublicAdministration::class)->state('active')->create([
            'rollup_id' => 1,
            'token_auth' => 'faketoken',
        ]);
        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
            'analytics_id' => 1,
        ]);
        $this->user = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->sync([$this->user->id => [
            'user_status' => UserStatus::ACTIVE,
            'user_email' => $this->faker->unique()->freeEmail,
        ]]);

        Bouncer::dontCache();
        Notification::fake();

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->user->assign(UserRole::ADMIN);
        });
    }

    /**
     * Test roll-up sites updated.
     */
    public function testWebsiteActivatedRollUpsUpdate(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::INFORMATIONAL;
            $this->website->save();
        });

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
                $mock->shouldReceive('updateRollUp')
                    ->withArgs([
                        $this->publicAdministration->rollup_id,
                        [$this->website->analytics_id],
                    ]);
                $mock->shouldReceive('setWebsiteAccess')
                    ->withArgs([
                        $this->publicAdministration->ipa_code,
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
            });
        });

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteActivated($this->website));
    }

    /**
     * Test roll-up sites throwing exception.
     */
    public function testWebsiteActivatedRollUpsUpdateFail(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::SERVICE;
            $this->website->save();
        });

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ])
                    ->andThrow(\Exception::class, 'Public rollup exception testing');
                $mock->shouldReceive('updateRollUp')
                    ->withArgs([
                        $this->publicAdministration->rollup_id,
                        [$this->website->analytics_id],
                    ])
                    ->andThrow(\Exception::class, 'Public administration rollup exception reporting');
                $mock->shouldNotReceive('setWebsiteAccess');
            });
        });

        $this->partialMock(HasAnalyticsDashboard::class)
            ->shouldReceive('addToRollUp')
            ->withArgs([$this->website])
            ->andThrow(\Exception::class, 'Public administration rollup exception reporting');

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->website->publicAdministration->ipa_code,
            ],
        ]);

        Log::shouldReceive('error')
            ->withSomeOfArgs('Public rollup exception testing');

        Log::shouldReceive('error')
            ->withSomeOfArgs('Public administration rollup exception reporting');

        event(new WebsiteActivated($this->website));
    }

    /**
     * Test roll-up sites update for primary website.
     */
    public function testPrimaryWebsiteActivatedRollUpsUpdate(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
                $mock->shouldNotReceive('updateRollUp');
                $mock->shouldNotReceive('setWebsiteAccess');
            });
        });

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteActivated($this->website));
    }

    public function testWebsiteAddedByUser(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::SERVICE;
            $this->website->save();
        });
        $secondUser = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->sync([$secondUser->id => [
            'user_status' => UserStatus::ACTIVE,
            'user_email' => $this->faker->unique()->freeEmail,
        ]], false);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($secondUser) {
            $secondUser->assign(UserRole::ADMIN);
        });

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getJavascriptSnippet')
                    ->withArgs([
                        $this->website->analytics_id,
                    ])
                    ->once()
                    ->andReturn('fakesnippet');
            });
        });

        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' added of type ' . $this->website->type->description,
                [
                    'event' => EventType::WEBSITE_ADDED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                    'user' => $this->user->uuid,
                ],
            ]
        );

        event(new WebsiteAdded($this->website, $this->user));

        Notification::assertSentTo(
            [$this->user],
            WebsiteAddedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals('fakesnippet', $mail->viewData['javascriptSnippet']);
                $this->assertEquals($mail->subject, __('Nuovo sito web aggiunto'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$secondUser],
            UserWebsiteAddedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $userEmailAddress = $secondUser->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Nuovo sito web aggiunto'));

                return $mail->hasTo($userEmailAddress, $secondUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserWebsiteAddedEmail::class
        );
    }

    public function testWebsiteAddedBySuperAdmin(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::MOBILE;
            $this->website->save();
        });
        $secondUser = factory(User::class)->state('active')->create();
        $this->publicAdministration->users()->sync([$secondUser->id], false);
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($secondUser) {
            $secondUser->assign(UserRole::ADMIN);
        });

        Bouncer::scope()->onceTo(0, function () {
            $this->user->assign(UserRole::SUPER_ADMIN);
        });

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getJavascriptSnippet')
                    ->withArgs([
                        $this->website->analytics_id,
                    ])
                    ->once()
                    ->andReturn('fakesnippet');
            });
        });

        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' added of type ' . $this->website->type->description,
                [
                    'event' => EventType::WEBSITE_ADDED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteAdded($this->website, $this->user));

        Notification::assertSentTo(
            [$this->user],
            WebsiteAddedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals('fakesnippet', $mail->viewData['javascriptSnippet']);
                $this->assertEquals($mail->subject, __('Nuovo sito web aggiunto'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$secondUser],
            UserWebsiteAddedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Nuovo sito web aggiunto'));

                return $mail->hasTo($secondUser->email, $secondUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserWebsiteAddedEmail::class
        );
    }

    public function testPrimaryWebsiteAdded(): void
    {
        $secondUser = factory(User::class)->state('active')->create();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($secondUser) {
            $this->user->assign(UserRole::ADMIN);
            $secondUser->assign(UserRole::ADMIN);
        });
        $this->publicAdministration->users()->sync([$secondUser->id], false);

        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('getJavascriptSnippet')
                    ->withArgs([
                        $this->website->analytics_id,
                    ])
                    ->once()
                    ->andReturn('fakesnippet');
            });
        });

        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' added of type ' . $this->website->type->description,
                [
                    'event' => EventType::WEBSITE_ADDED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                    'user' => $this->user->uuid,
                ],
            ]
        );

        event(new WebsiteAdded($this->website, $this->user));

        Notification::assertNotSentTo(
            [$this->user],
            WebsiteAddedEmail::class
        );

        Notification::assertSentTo(
            [$secondUser],
            UserWebsiteAddedEmail::class,
            function ($notification, $channels) use ($secondUser) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($secondUser)->build();
                $this->assertEquals($secondUser->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Nuovo sito web aggiunto'));

                return $mail->hasTo($secondUser->email, $secondUser->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->user],
            UserWebsiteAddedEmail::class
        );
    }

    public function testWebsiteActivatedWithRTD(): void
    {
        $this->app['env'] = 'production';

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->once()
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
            });
        });

        event(new WebsiteActivated($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Sito web attivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$this->publicAdministration],
            RTDWebsiteActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->publicAdministration)->build();
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Sito web attivato'));

                return $mail->hasTo($this->publicAdministration->rtd_mail, $this->publicAdministration->rtd_name);
            }
        );

        $this->app['env'] = 'testing';
    }

    /*
     * Test website activated on public playground with rtd
     */
    public function testWebsiteActivatedOnPublicPlaygroundWithRTD(): void
    {
        $this->app['env'] = 'public-playground';

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->once()
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
            });
        });

        event(new WebsiteActivated($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Sito web attivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDWebsiteActivatedEmail::class
        );

        $this->app['env'] = 'testing';
    }

    public function testWebsiteActivatedWithoutRTD(): void
    {
        Event::fakeFor(function () {
            $this->publicAdministration->rtd_mail = null;
            $this->publicAdministration->rtd_name = null;
            $this->publicAdministration->save();
        });

        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('setWebsiteAccess')
                    ->once()
                    ->withArgs([
                        'anonymous',
                        WebsiteAccessType::VIEW,
                        $this->website->analytics_id,
                    ]);
            });
        });

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteActivated($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Sito web attivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDWebsiteActivatedEmail::class
        );
    }

    public function testWebsiteArchivedManually(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' archived manually',
            [
                'event' => EventType::WEBSITE_ARCHIVED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteArchived($this->website, true));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteArchivedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertTrue($mail->viewData['manually']);
                $this->assertEquals($mail->subject, __('Sito web archiviato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testWebsiteArchivedAutomatically(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' archived due to inactivity',
            [
                'event' => EventType::WEBSITE_ARCHIVED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteArchived($this->website, false));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteArchivedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertFalse($mail->viewData['manually']);
                $this->assertEquals($mail->subject, __('Sito web archiviato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    /**
     * Test website unarchived event handler.
     */
    public function testWebsiteUnarchived(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' unarchived manually',
            [
                'event' => EventType::WEBSITE_UNARCHIVED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteUnarchived($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteUnarchivedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Sito web riattivato'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testPrimaryWebsiteNotTracking(): void
    {
        $this->expectLogMessage('notice', [
            'Primary website ' . $this->website->info . ' tracking inactive.',
            [
                'event' => EventType::PRIMARY_WEBSITE_NOT_TRACKING,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PrimaryWebsiteNotTracking($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserPrimaryWebsiteNotTrackingEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($mail->subject, __('[Attenzione] - Tracciamento sito istituzionale non attivo'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testWebsiteArchiving(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' reported as not active and scheduled for archiving',
            [
                'event' => EventType::WEBSITE_ARCHIVING,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteArchiving($this->website, 2));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteArchivingEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals(2, $mail->viewData['daysLeft']);
                $this->assertEquals($mail->subject, __('[Attenzione] - Sito web in archiviazione'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    /**
     * Test website scheduled for purging event handler.
     */
    public function testWebsitePurging(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' scheduled purging',
            [
                'event' => EventType::WEBSITE_PURGING,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsitePurging($this->website));

        Notification::assertSentTo(
            [$this->user],
            UserWebsitePurgingEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('[Attenzione] - Sito web in eliminazione'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testWebsitePurged(): void
    {
        Event::fakeFor(function () {
            $this->website->type = WebsiteType::MOBILE;
            $this->website->save();
        });

        Cache::shouldReceive('forget')->once()->withArgs([Website::WEBSITE_COUNT_KEY]);

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' purged',
            [
                'event' => EventType::WEBSITE_PURGED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsitePurged($this->website->toJson(), $this->publicAdministration->toJson()));

        Notification::assertSentTo(
            [$this->user],
            UserWebsitePurgedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']->slug);
                $this->assertEquals($mail->subject, __('[Attenzione] - Sito web eliminato'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );
    }

    public function testPrimaryWebsitePurged(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' purged',
            [
                'event' => EventType::WEBSITE_PURGED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsitePurged($this->website->toJson(), $this->publicAdministration->toJson()));

        Notification::assertNotSentTo(
            [$this->user],
            UserWebsitePurgedEmail::class
        );
    }

    public function testWebsiteRestored(): void
    {
        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' restored.',
            [
                'event' => EventType::WEBSITE_RESTORED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteRestored($this->website));
    }

    /**
     * Test website updated event handler.
     */
    public function testWebsiteUpdated(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' updated',
            [
                'event' => EventType::WEBSITE_UPDATED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteUpdated($this->website));
    }

    public function testWebsiteUrlChanged(): void
    {
        $this->expectLogMessage('notice', [
            'Website' . $this->website->info . ' URL updated from ' . e('https://oldurl.local') . ' to ' . e($this->website->url),
            [
                'event' => EventType::WEBSITE_URL_CHANGED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteUrlChanged($this->website, 'https://oldurl.local'));

        Notification::assertSentTo(
            [$this->user],
            UserWebsiteUrlChangedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->website->slug, $mail->viewData['website']['slug']);
                $this->assertEquals($mail->subject, __('Modifica URL sito web'));

                return $mail->hasTo($this->user->email, $this->user->full_name);
            }
        );
    }

    public function testWebsiteStatusChanged(): void
    {
        Cache::shouldReceive('forget')->once()->withArgs([Website::WEBSITE_COUNT_KEY]);

        $this->expectLogMessage('notice', [
            'Website ' . $this->website->info . ' status changed from "' . WebsiteStatus::getDescription(WebsiteStatus::ARCHIVED) . '" to "' . $this->website->status->description . '"',
            [
                'event' => EventType::WEBSITE_STATUS_CHANGED,
                'website' => $this->website->id,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new WebsiteStatusChanged($this->website, WebsiteStatus::ARCHIVED));
    }

    /**
     * Test website added event handler.
     */
    public function testWebsiteAdded(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' added of type ' . $this->website->type->description,
                [
                    'event' => EventType::WEBSITE_ADDED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                    'user' => $this->user->uuid,
                ],
            ]
        );

        event(new WebsiteAdded($this->website, $this->user));
    }

    /**
     * Test website activated event handler.
     */
    public function testWebsiteActivated(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' activated',
                [
                    'event' => EventType::WEBSITE_ACTIVATED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteActivated($this->website));
    }

    /**
     * Test website archived event handler.
     */
    public function testWebsiteArchivedForInactivity(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' archived due to inactivity',
                [
                    'event' => EventType::WEBSITE_ARCHIVED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteArchived($this->website, false));
    }

    /**
     * Test website manually deleted event handler.
     */
    public function testWebsiteDeleted(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' deleted.',
                [
                    'event' => EventType::WEBSITE_DELETED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteDeleted($this->website));
    }

    /**
     * Test primary website inactive event handler.
     */
    public function testPrimaryWebsiteInactive(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Primary website ' . $this->website->info . ' tracking inactive.',
                [
                    'event' => EventType::PRIMARY_WEBSITE_NOT_TRACKING,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new PrimaryWebsiteNotTracking($this->website));
    }
}
