<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

/**
 * Analytics Service command error exception.
 */
class CommandErrorException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->error('Analytics Service command error: ' . $this->getMessage());
        // TODO: Notify me!!
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return \Illuminate\Http\Response the response
     */
    public function render(): Response
    {
        logger()->error($this->getMessage());

        return redirect()->home()->withMessage(['error' => 'Il comando inviato al servizio di Analytics ha ritornato un errore. Riprovare successivamente.']); //TODO: put message in lang file
    }
}
