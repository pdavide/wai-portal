<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use App\Traits\SendsResponse;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Kong Service connection exception.
 */
class ApiGatewayServiceException extends Exception
{
    use SendsResponse;

    /**
     * Report the exception.
     */
    public function report(): void
    {
        logger()->critical(
            'API Gateway Service exception: ' . $this->getMessage(),
            [
                'event' => EventType::EXCEPTION,
                'exception_type' => ExceptionType::API_GATEWAY_SERVICE,
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
        return $this->errorResponse('API Gateway error', ExceptionType::API_GATEWAY_SERVICE, 500);
    }
}
