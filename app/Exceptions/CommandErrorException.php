<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use App\Traits\SendsResponse;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Analytics Service command error exception.
 */
class CommandErrorException extends Exception
{
    use SendsResponse;

    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->critical(
            'Analytics Service command error: ' . $this->getMessage(),
            [
                'event' => EventType::EXCEPTION,
                'exception_type' => ExceptionType::ANALYTICS_COMMAND,
                'exception' => $this,
            ]
        );
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return RedirectResponse|JsonResponse the response
     */
    public function render()
    {
        return $this->errorResponse('Anaytics command error', ExceptionType::ANALYTICS_COMMAND, 500);
    }
}
