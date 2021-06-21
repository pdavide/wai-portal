<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use Illuminate\Mail\Events\MessageSent;
use Tests\TestCase;

/**
 * Email message events listener tests.
 */
class LogSentMessageTest extends TestCase
{
    /**
     * Test email message sent event handler.
     */
    public function testLogSentMessage(): void
    {
        $message = (new \Swift_Message('Test subject'))->setTo('fake@example.local', 'Fake receiver');

        $anonymizedMailAddresses = collect($message->getTo())->keys()->map(function ($address) {
            return preg_replace('/(?<=.).(?=.*.@)/', '*', $address);
        })->toArray();

        $this->expectLogMessage('debug', [
            'Mail message with subject "' . $message->getSubject() . '" sent to ' . implode(', ', $anonymizedMailAddresses),
           ['event' => EventType::MAIL_SENT],
        ]);

        event(new MessageSent($message));
    }
}
