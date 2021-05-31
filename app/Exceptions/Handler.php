<?php

namespace App\Exceptions;

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Italia\SPIDAuth\Exceptions\SPIDLoginAnomalyException;
use Italia\SPIDAuth\Exceptions\SPIDLoginException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Base exceptions handler.
 */
class Handler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param Exception $exception the raised exception
     *
     * @throws Exception if an error occurred during reporting
     */
    public function report(Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $user = auth()->check() ? 'User ' . auth()->user()->uuid : 'Anonymous user';
            $statusCode = $exception->getStatusCode();

            if ($statusCode < 500) {
                logger()->info(
                    'A client error (status code: ' . $statusCode . ') occurred [' . request()->url() . ' visited by ' . $user . '].',
                    [
                        'event' => EventType::EXCEPTION,
                        'exception_type' => ExceptionType::HTTP_CLIENT_ERROR,
                        'exception' => $exception,
                    ]
                );
            } else {
                logger()->error(
                    'A server error (status code: ' . $statusCode . ') occurred [' . request()->url() . ' visited by ' . $user . '].',
                    [
                        'event' => EventType::EXCEPTION,
                        'exception_type' => ExceptionType::SERVER_ERROR,
                        'exception' => $exception,
                    ]
                );
            }
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request the request
     * @param Exception $exception the raised exception
     *
     * @return mixed the response
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            return redirect()->home()->withNotification([
                'title' => __('sessione scaduta'),
                'message' => implode("\n", [
                    __('La sessione è scaduta a causa di inattività sulla pagina.'),
                    __('Accedi di nuovo per continuare da dove eri rimasto/a.'),
                ]),
                'status' => 'warning',
                'icon' => 'it-error',
            ]);
        }

        if ($exception instanceof InvalidSignatureException) {
            return response()->view('errors.403', [
                'userMessage' => __('Il link che hai usato non è valido oppure è scaduto.'),
                'exception' => $exception,
            ], $exception->getStatusCode());
        }

        if ($exception instanceof SPIDLoginException) {
            $message = $exception instanceof SPIDLoginAnomalyException
                ? ucfirst($exception->getUserMessage()) . '.'
                : __('auth.spid_failed');

            return redirect()->home()->withNotification([
                'title' => __('accesso non effettuato'),
                'message' => $message,
                'status' => 'error',
                'icon' => 'it-close-circle',
            ]);
        }

        if (($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException)
            && $request->expectsJson()) {
            return response()->json([
                'error' => 'not_found',
                'error_description' => 'The requested resource cannot be found on this server',
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException && $request->expectsJson()) {
            return response()->json([
                'error' => 'method_not_allowed',
                'error_description' => 'The http method used is not allowed for the requested resource',
            ], 405);
        }

        return parent::render($request, $exception);
    }

    /**
     * Get the context variables for logging.
     *
     * @return array the context variables
     */
    protected function context(): array
    {
        $context = [
            'event' => EventType::EXCEPTION,
            'exception_type' => ExceptionType::GENERIC,
        ];
        try {
            if (auth()->check()) {
                $context['user'] = auth()->user()->uuid;
            }
        } catch (Throwable $t) {
        }

        return $context;
    }
}
