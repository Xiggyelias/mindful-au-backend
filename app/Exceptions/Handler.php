<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    private const GENERIC_SERVER_ERROR_MESSAGE = 'An unexpected error occurred.';
    private const VALIDATION_ERROR_MESSAGE = 'The given data was invalid.';

    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if (!$this->shouldRenderApiResponse($request)) {
                return null;
            }

            return $this->renderApiException($request, $e);
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    private function shouldRenderApiResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            $response = $e->getResponse();
            $status = max(400, min(599, $response->getStatusCode()));

            if ($status >= 500) {
                return $this->renderApiServerError($request, $e, $status);
            }

            $payload = [
                'message' => $this->clientMessageForStatus($status),
            ];

            if ($status === 422 && $response instanceof JsonResponse) {
                $raw = $response->getData(true);
                if (is_array($raw) && isset($raw['errors']) && is_array($raw['errors'])) {
                    $payload['errors'] = $raw['errors'];
                }
            }

            return response()->json($payload, $status);
        }

        if ($e instanceof ValidationException) {
            $status = (int) ($e->status ?? 422);
            return response()->json([
                'message' => self::VALIDATION_ERROR_MESSAGE,
                'errors' => $e->errors(),
            ], $status);
        }

        if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            Log::warning('404 Not Found Exception caught in Handler:', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'message' => 'Method not allowed.',
            ], 405);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return response()->json([
                'message' => 'Too many requests.',
            ], 429);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = max(400, min(599, $e->getStatusCode()));

            if ($status >= 500) {
                return $this->renderApiServerError($request, $e, $status);
            }

            return response()->json([
                'message' => $this->clientMessageForStatus($status),
            ], $status);
        }

        return $this->renderApiServerError($request, $e, 500);
    }

    private function renderApiServerError(Request $request, Throwable $e, int $status): JsonResponse
    {
        $errorId = (string) Str::uuid();

        Log::error('Unhandled API exception', [
            'error_id' => $errorId,
            'status' => $status,
            'path' => $request->path(),
            'method' => $request->method(),
            'exception' => $e::class,
            'exception_message_hash' => hash('sha256', (string) $e->getMessage()),
        ]);

        $message = $status >= 500
            ? (HttpResponse::$statusTexts[$status] ?? self::GENERIC_SERVER_ERROR_MESSAGE)
            : self::GENERIC_SERVER_ERROR_MESSAGE;

        $payload = [
            'message' => $message,
            'error_id' => $errorId,
        ];

        if ($this->shouldExposeApiErrorDetails()) {
            $payload['exception'] = $e::class;
            $payload['detail'] = $e->getMessage();
        }

        return response()->json($payload, $status);
    }

    private function shouldExposeApiErrorDetails(): bool
    {
        $appEnv = Str::lower((string) config('app.env', env('APP_ENV', 'production')));
        if ($appEnv === 'production') {
            return false;
        }

        $rawExposure = getenv('API_EXPOSE_ERROR_DETAILS');
        if ($rawExposure === false) {
            $rawExposure = env('API_EXPOSE_ERROR_DETAILS', false);
        }

        $allowExposure = filter_var($rawExposure, FILTER_VALIDATE_BOOL);
        return $allowExposure && (bool) config('app.debug');
    }

    private function clientMessageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Unauthenticated.',
            403 => 'Forbidden.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict.',
            410 => 'Resource not found.',
            415 => 'Unsupported media type.',
            422 => self::VALIDATION_ERROR_MESSAGE,
            429 => 'Too many requests.',
            default => HttpResponse::$statusTexts[$status] ?? 'Request failed.',
        };
    }
}
