<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Events\Jobs\WebsitesMonitoringCheckCompleted;
use Tests\TestCase;

/**
 * Active websites monitoring job events listener tests.
 */
class CheckWebsitesMonitoringJobEventsSubscriberTest extends TestCase
{
    /**
     * Test job completed event handler.
     */
    public function testWebsitesMonitoringCheckCompleted(): void
    {
        $archived = [
            [
                'website' => 'fake-archived',
            ],
        ];
        $archiving = [
            [
                'website' => 'fake-archiving',
            ],
        ];
        $failed = [
            [
                'website' => 'fake-failed',
            ],
        ];

        $this->expectLogMessage('notice', [
            'Website tracking check completed: ' . count($archiving) . ' website/s not tracking ' . count($archived) . ' website/s archived, ' . count($failed) . ' website/s check failed',
            ['event' => EventType::TRACKING_WEBSITES_CHECK_COMPLETED],
        ]);

        event(new WebsitesMonitoringCheckCompleted($archived, $archiving, $failed));
    }
}
