<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Operation not allowed exception.
 */
class InvalidCredentialException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->error('Credential not found: ' . $this->getMessage(),
            [
                'event' => EventType::EXCEPTION,
                'exception_type' => ExceptionType::INVALID_OPERATION,
                'exception' => $this,
            ]
        );
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return RedirectResponse the response
     */
    public function render(): RedirectResponse
    {
        return redirect()->home()->withNotification([
            'title' => __('credenziale non trovata'),
            'message' => __('La credenziale selezionata non è stata trovata.'),
            'status' => 'error',
            'icon' => 'it-close-circle',
        ]);
    }
}
