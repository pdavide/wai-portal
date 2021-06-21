<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Events\Jobs\PublicAdministrationsUpdateFromIpaCompleted;
use Tests\TestCase;

/**
 * Public administrations index update events listener tests.
 */
class UpdatePublicAdministrationsFromIpaJobEventsSubscriberTest extends TestCase
{
    /**
     * Test job completed event handler.
     */
    public function testUpdateFromIpaCompleted(): void
    {
        $updates = [
            'fakeIpa' => [],
        ];

        $this->expectLogMessage('notice', [
            'Completed update of Public administrations from IPA index: ' . count($updates) . ' registered Public Administration/s updated',
            [
                'event' => EventType::UPDATE_PA_FROM_IPA_COMPLETED,
            ],
        ]);

        event(new PublicAdministrationsUpdateFromIpaCompleted($updates));
    }
}
