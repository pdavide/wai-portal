<?php

namespace Tests\Feature;

use App\Enums\WebsiteStatus;
use App\Events\Website\WebsiteStatusChanged;
use App\Events\Website\WebsiteUrlChanged;
use App\Models\PublicAdministration;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Website updates events listener tests.
 */
class WebsiteUpdatesSubscriberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The website.
     *
     * @var Website the website
     */
    private $website;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([WebsiteStatusChanged::class, WebsiteUrlChanged::class]);
        $publicAdministration = factory(PublicAdministration::class)->create();
        $this->website = factory(Website::class)->state('active')->create([
            'url' => 'https://fakeurl.local',
            'slug' => Str::slug('https://fakeurl.local'),
            'public_administration_id' => $publicAdministration->id,
        ]);
    }

    /**
     * Test website status changed event handler.
     */
    public function testWebsiteStatusChanged(): void
    {
        $this->website->status = WebsiteStatus::ARCHIVED;
        $this->website->save();

        Event::assertDispatched(WebsiteStatusChanged::class, function ($event) {
            $website = $event->getWebsite();

            return $this->website->id === $website->id
                && $website->status->is(WebsiteStatus::ARCHIVED)
                && $event->getOldStatus()->is(WebsiteStatus::ACTIVE);
        });

        Event::assertNotDispatched(WebsiteUrlChanged::class);
    }

    /**
     * Test website URL changed event handler.
     */
    public function testWebsiteURLChanged(): void
    {
        $this->website->url = 'https://newfakeurl.local';
        $this->website->slug = Str::slug($this->website->url);
        $this->website->save();

        Event::assertDispatched(WebsiteUrlChanged::class, function ($event) {
            $website = $event->getWebsite();

            return $this->website->id === $website->id
                && 'https://newfakeurl.local' === $website->url
                && 'https://fakeurl.local' === $event->getOldUrl();
        });

        Event::assertNotDispatched(WebsiteStatusChanged::class);
    }
}
