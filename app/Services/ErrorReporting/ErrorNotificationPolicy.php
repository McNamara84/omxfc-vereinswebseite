<?php

namespace App\Services\ErrorReporting;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorNotificationPolicy
{
    public function shouldNotify(Throwable $exception): bool
    {
        if (! config('error-reporting.enabled', false)) {
            return false;
        }

        if (! app()->environment((string) config('error-reporting.environment', 'production'))) {
            return false;
        }

        if (Context::get('error_notification_delivery') === true) {
            return false;
        }

        if ($exception instanceof ShouldntReport) {
            return false;
        }

        if ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException
            || $exception instanceof TokenMismatchException
            || $exception instanceof ModelNotFoundException) {
            return false;
        }

        if ($exception instanceof HttpExceptionInterface) {
            if ($exception->getStatusCode() === 503 && app()->isDownForMaintenance()) {
                return false;
            }

            return $exception->getStatusCode() >= 500;
        }

        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse()->getStatusCode() >= 500;
        }

        return true;
    }
}
