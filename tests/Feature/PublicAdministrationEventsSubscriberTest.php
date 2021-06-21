<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\PublicAdministrationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteType;
use App\Events\PublicAdministration\PublicAdministrationActivated;
use App\Events\PublicAdministration\PublicAdministrationActivationFailed;
use App\Events\PublicAdministration\PublicAdministrationNotFoundInIpa;
use App\Events\PublicAdministration\PublicAdministrationPrimaryWebsiteUpdated;
use App\Events\PublicAdministration\PublicAdministrationPurged;
use App\Events\PublicAdministration\PublicAdministrationRegistered;
use App\Events\PublicAdministration\PublicAdministrationUpdated;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use App\Notifications\PublicAdministrationActivatedEmail;
use App\Notifications\PublicAdministrationPurgedEmail;
use App\Notifications\PublicAdministrationRegisteredEmail;
use App\Notifications\RTDEmailAddressChangedEmail;
use App\Notifications\RTDPublicAdministrationRegisteredEmail;
use App\Notifications\SuperAdminPublicAdministrationNotFoundInIpaEmail;
use App\Services\MatomoService;
use App\Traits\ManageRecipientNotifications;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Public administrations events listener tests.
 */
class PublicAdministrationEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use ManageRecipientNotifications;

    /**
     * The public administration.
     *
     * @var PublicAdministration the public administration
     */
    public $publicAdministration;

    /**
     * The website.
     *
     * @var Website the website
     */
    public $website;

    /**
     * The user.
     *
     * @var User the user
     */
    public $user;

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
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->user = factory(User::class)->create();
        $this->publicAdministration->users()->sync([$this->user->id => [
            'user_status' => UserStatus::ACTIVE,
            'user_email' => $this->faker->unique()->freeEmail,
        ]]);
        $this->publicAdministration->save();
        $this->website = factory(Website::class)->create([
            'type' => WebsiteType::INSTITUTIONAL,
            'analytics_id' => 1,
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Notification::fake();
    }

    /**
     * Test roll-up registering successful on public administration activation.
     */
    public function testPublicAdministrationActivate(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('registerRollUp')
                    ->once()
                    ->andReturn(1);

                $mock->shouldReceive('registerUser')
                    ->once();

                $mock->shouldReceive('getUserAuthToken')
                    ->once()
                    ->andReturn('faketoken');

                $mock->shouldReceive('setWebsiteAccess')
                    ->once();

                $mock->shouldReceive('setWebsiteAccess')
                    ->once();
            });
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' activated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivated($this->publicAdministration));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationActivatedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione attivata'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );
    }

    /**
     * Test roll-up registering throwing exception.
     */
    public function testPublicAdministrationActivatedRollUpRegisteringFail(): void
    {
        $this->app->bind('analytics-service', function () {
            return $this->partialMock(MatomoService::class, function ($mock) {
                $mock->shouldReceive('registerRollUp')
                    ->andThrow(\Exception::class, 'Public administration roll-up exception testing');
            });
        });

        Log::shouldReceive('error')
            ->withSomeOfArgs('Public administration roll-up exception testing');

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' activated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivated($this->publicAdministration));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationActivatedEmail::class
        );
    }

    /**
     * Test public administration registered with email sent to RTD.
     */
    public function testPublicAdministrationRegisteredWithRTD(): void
    {
        $this->app['env'] = 'production';

        Cache::shouldReceive('forget')->once()->withArgs([
            PublicAdministration::PUBLIC_ADMINISTRATION_COUNT_KEY,
        ]);

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

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' registered Public Administration ' . $this->publicAdministration->info,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_REGISTERED,
                'pa' => $this->publicAdministration->ipa_code,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new PublicAdministrationRegistered($this->publicAdministration, $this->user));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationRegisteredEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals('fakesnippet', $mail->viewData['javascriptSnippet']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione registrata'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );

        Notification::assertSentTo(
            [$this->publicAdministration],
            RTDPublicAdministrationRegisteredEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->publicAdministration)->build();
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($this->user->uuid, $mail->viewData['registeringUser']['uuid']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione registrata'));

                return $mail->hasTo($this->publicAdministration->rtd_mail, $this->publicAdministration->rtd_name);
            }
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration registered on public playground with email sent to RTD.
     */
    public function testPublicAdministrationRegisteredOnPublicPlaygroundWithRTD(): void
    {
        $this->app['env'] = 'public-playground';

        Cache::shouldReceive('forget')->once()->withArgs([
            PublicAdministration::PUBLIC_ADMINISTRATION_COUNT_KEY,
        ]);

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

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' registered Public Administration ' . $this->publicAdministration->info,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_REGISTERED,
                'pa' => $this->publicAdministration->ipa_code,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new PublicAdministrationRegistered($this->publicAdministration, $this->user));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationRegisteredEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals('fakesnippet', $mail->viewData['javascriptSnippet']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione registrata'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDPublicAdministrationRegisteredEmail::class
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration registered without email to RTD.
     */
    public function testPublicAdministrationRegisteredWithoutRTD(): void
    {
        Event::fakeFor(function () {
            $this->publicAdministration->rtd_mail = null;
            $this->publicAdministration->rtd_name = null;
            $this->publicAdministration->save();
        });

        Cache::shouldReceive('forget')->once()->withArgs([
            PublicAdministration::PUBLIC_ADMINISTRATION_COUNT_KEY,
        ]);

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

        $this->expectLogMessage('notice', [
            'User ' . $this->user->uuid . ' registered Public Administration ' . $this->publicAdministration->info,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_REGISTERED,
                'pa' => $this->publicAdministration->ipa_code,
                'user' => $this->user->uuid,
            ],
        ]);

        event(new PublicAdministrationRegistered($this->publicAdministration, $this->user));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationRegisteredEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals('fakesnippet', $mail->viewData['javascriptSnippet']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione registrata'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDPublicAdministrationRegisteredEmail::class
        );
    }

    /**
     * Test public administration purged.
     */
    public function testPublicAdministrationPurged(): void
    {
        Cache::shouldReceive('forget')->once()->withArgs([PublicAdministration::PUBLIC_ADMINISTRATION_COUNT_KEY]);

        $this->expectLogMessage('notice', [
            'Public Administration "' . $this->publicAdministration->name . '" [' . $this->publicAdministration->ipa_code . '] purged',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_PURGED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);
        $userEmailForPublicAdministration = $this->getUserEmailForPublicAdministration($this->user, $this->publicAdministration);
        event(new PublicAdministrationPurged($this->publicAdministration->toJson(), $this->user, $userEmailForPublicAdministration));

        Notification::assertSentTo(
            [$this->user],
            PublicAdministrationPurgedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->user)->build();
                $userEmailAddress = $this->user->getEmailForPublicAdministration($this->publicAdministration);
                $this->assertEquals($this->user->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']->ipa_code);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione eliminata'));

                return $mail->hasTo($userEmailAddress, $this->user->full_name);
            }
        );
    }

    /**
     * Test public administration primary website updated.
     */
    public function testPublicAdministrationPrimaryWebsiteUpdated(): void
    {
        $this->expectLogMessage('warning', [
            'Public Administration ' . $this->publicAdministration->info . ' primary website was changed in IPA index [' . e('https://newurl.local') . '].',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_PRIMARY_WEBSITE_CHANGED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationPrimaryWebsiteUpdated($this->publicAdministration, $this->website, 'https://newurl.local'));
    }

    /**
     * Test public administration updated.
     */
    public function testPublicAdministrationUpdated(): void
    {
        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, []));

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDEmailAddressChangedEmail::class,
        );
    }

    /**
     * Test public administration updated with RTD email update.
     */
    public function testPendingPublicAdministrationUpdatedWithRTDChange(): void
    {
        $this->app['env'] = 'production';

        Event::fakeFor(function () {
            $this->user->publicAdministrations()->sync([$this->publicAdministration->id => ['user_status' => UserStatus::PENDING]]);
            $this->user->setCreatedAt(now());
            $this->user->save();
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, ['rtd_mail' => ['old' => 'old@example.local', 'new' => 'new@example.local']]));

        Notification::assertSentTo(
            [$this->publicAdministration],
            RTDEmailAddressChangedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->publicAdministration)->build();
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($this->user->uuid, $mail->viewData['earliestRegisteredAdministrator']['uuid']);
                $this->assertEquals($mail->subject, __('Nuovo indirizzo email RTD'));

                return $mail->hasTo($this->publicAdministration->rtd_mail, $this->publicAdministration->rtd_name);
            }
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration updated on public playground with RTD email update.
     */
    public function testPendingPublicAdministrationUpdatedOnPublicPlaygroundWithRTDChange(): void
    {
        $this->app['env'] = 'public-playground';

        Event::fakeFor(function () {
            $this->user->status = UserStatus::PENDING;
            $this->user->setCreatedAt(now());
            $this->user->save();
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, ['rtd_mail' => ['old' => 'old@example.local', 'new' => 'new@example.local']]));

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDEmailAddressChangedEmail::class,
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration updated with RTD email update.
     */
    public function testActivePublicAdministrationUpdatedWithRTDChange(): void
    {
        $this->app['env'] = 'production';

        $invitedAdmin = factory(User::class)->state('invited')->create();
        $secondAdmin = factory(User::class)->state('active')->create();

        Bouncer::dontCache();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($invitedAdmin, $secondAdmin) {
            $this->user->assign(UserRole::ADMIN);
            $invitedAdmin->assign(UserRole::ADMIN);
            $secondAdmin->assign(UserRole::ADMIN);
        });

        Event::fakeFor(function () use ($invitedAdmin, $secondAdmin) {
            $this->user->status = UserStatus::ACTIVE;
            $this->user->setCreatedAt(now()->subDay());
            $this->user->save();

            $secondAdmin->setCreatedAt(now());
            $secondAdmin->save();

            $this->publicAdministration->status = PublicAdministrationStatus::ACTIVE;
            $this->publicAdministration->users()->sync([$secondAdmin->id, $invitedAdmin->id], false);
            $this->publicAdministration->save();
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, ['rtd_mail' => ['old' => 'old@example.local', 'new' => 'new@example.local']]));

        Notification::assertSentTo(
            [$this->publicAdministration],
            RTDEmailAddressChangedEmail::class,
            function ($notification, $channels) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($this->publicAdministration)->build();
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($this->user->uuid, $mail->viewData['earliestRegisteredAdministrator']['uuid']);
                $this->assertEquals($mail->subject, __('Nuovo indirizzo email RTD'));

                return $mail->hasTo($this->publicAdministration->rtd_mail, $this->publicAdministration->rtd_name);
            }
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration updated on public playground with RTD email update.
     */
    public function testActivePublicAdministrationUpdatedOnPublicPlaygroundWithRTDChange(): void
    {
        $this->app['env'] = 'public-playground';

        $invitedAdmin = factory(User::class)->state('invited')->create();
        $secondAdmin = factory(User::class)->state('active')->create();

        Bouncer::dontCache();
        Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($invitedAdmin, $secondAdmin) {
            $this->user->assign(UserRole::ADMIN);
            $invitedAdmin->assign(UserRole::ADMIN);
            $secondAdmin->assign(UserRole::ADMIN);
        });

        Event::fakeFor(function () use ($invitedAdmin, $secondAdmin) {
            $this->user->status = UserStatus::ACTIVE;
            $this->user->setCreatedAt(now()->subDay());
            $this->user->save();

            $secondAdmin->setCreatedAt(now());
            $secondAdmin->save();

            $this->publicAdministration->status = PublicAdministrationStatus::ACTIVE;
            $this->publicAdministration->users()->sync([$secondAdmin->id, $invitedAdmin->id], false);
            $this->publicAdministration->save();
        });

        $this->expectLogMessage('notice', [
            'Public Administration ' . $this->publicAdministration->info . ' updated',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationUpdated($this->publicAdministration, ['rtd_mail' => ['old' => 'old@example.local', 'new' => 'new@example.local']]));

        Notification::assertNotSentTo(
            [$this->publicAdministration],
            RTDEmailAddressChangedEmail::class,
        );

        $this->app['env'] = 'testing';
    }

    /**
     * Test public administration not found in iPA.
     */
    public function testPublicAdministrationNotFoundInIpa(): void
    {
        Bouncer::dontCache();
        $activeSuperAdmin = factory(User::class)->state('active')->create();
        $invitedSuperAdmin = factory(User::class)->state('invited')->create();
        Bouncer::scope()->onceTo(0, function () use ($activeSuperAdmin, $invitedSuperAdmin) {
            $activeSuperAdmin->assign(UserRole::SUPER_ADMIN);
            $invitedSuperAdmin->assign(UserRole::SUPER_ADMIN);
        });

        $this->expectLogMessage('warning', [
            'Public Administration ' . $this->publicAdministration->info . ' not found',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_UPDATED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationNotFoundInIpa($this->publicAdministration));

        Notification::assertSentTo(
            [$activeSuperAdmin],
            SuperAdminPublicAdministrationNotFoundInIpaEmail::class,
            function ($notification, $channels) use ($activeSuperAdmin) {
                $this->assertEquals($channels, ['mail']);
                $mail = $notification->toMail($activeSuperAdmin)->build();
                $this->assertEquals($activeSuperAdmin->uuid, $mail->viewData['user']['uuid']);
                $this->assertEquals($this->publicAdministration->ipa_code, $mail->viewData['publicAdministration']['ipa_code']);
                $this->assertEquals($mail->subject, __('Pubblica amministrazione non trovata in IPA'));

                return $mail->hasTo($activeSuperAdmin->email, $activeSuperAdmin->full_name);
            }
        );

        Notification::assertNotSentTo(
            [$invitedSuperAdmin],
            SuperAdminPublicAdministrationNotFoundInIpaEmail::class
        );
    }

    /**
     * Test public administration registered event handler.
     */
    public function testPublicAdministrationRegistered(): void
    {
        $user = factory(User::class)->create();

        $this->expectLogMessage('notice', [
            'User ' . $user->uuid . ' registered Public Administration ' . $this->publicAdministration->info,
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_REGISTERED,
                'pa' => $this->publicAdministration->ipa_code,
                'user' => $user->uuid,
            ],
        ]);

        event(new PublicAdministrationRegistered($this->publicAdministration, $user));
    }

    /**
     * Test public administration activation fail.
     */
    public function testPublicAdministrationActivationFailed(): void
    {
        $this->expectLogMessage('error', [
            'Public Administration ' . $this->publicAdministration->info . ' activation failed: Fake activation error message',
            [
                'event' => EventType::PUBLIC_ADMINISTRATION_ACTIVATION_FAILED,
                'pa' => $this->publicAdministration->ipa_code,
            ],
        ]);

        event(new PublicAdministrationActivationFailed($this->publicAdministration, 'Fake activation error message'));
    }
}
