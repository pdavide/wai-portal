<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use Exception;
use Illuminate\Http\RedirectResponse;

class TenantIdNotSetException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->error(
            'Tenant id is not set in the user session: ' . $this->getMessage(),
            [
                'event' => EventType::EXCEPTION,
                'exception_type' => ExceptionType::TENANT_SELECTION,
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
            'title' => __('errore del server'),
            'message' => implode("\n", [
                __('Qualcosa non ha funzionato nella gestione della sessione.'),
                __("Prova ad eseguire nuovamente l'accesso."),
            ]),
            'status' => 'error',
            'icon' => 'it-close-circle',
        ]);
    }
}
