<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Analytics Service connection exception.
 */
class AnalyticsServiceException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->error(
            'Analytics Service exception: ' . $this->getMessage(),
            [
                'event' => EventType::EXCEPTION,
                'type' => ExceptionType::ANALYTICS_SERVICE,
                'exception' => $this,
            ]
        );
        // TODO: Notify me!!
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return RedirectResponse the response
     */
    public function render(): RedirectResponse
    {
        return redirect()->home()->withMessage(['error' => 'Il servizio remoto di Analytics non è disponibile. Riprovare successivamente.']); //TODO: put message in lang file
    }
}
