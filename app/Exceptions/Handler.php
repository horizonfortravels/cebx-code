<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function render($request, Throwable $exception): Response
    {
        $isBrowserRequest = ! $request->expectsJson() && ! $request->is('api/*');
        $isHandledClientException = $exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException
            || $exception instanceof NotFoundHttpException;

        if ($isBrowserRequest && ! $isHandledClientException) {
            $statusCode = method_exists($exception, 'getStatusCode')
                ? (int) $exception->getStatusCode()
                : 500;

            if ($statusCode >= 500) {
                return response()->view('errors.500', [
                    'exception' => $exception,
                ], 500);
            }
        }

        return parent::render($request, $exception);
    }

    public function register(): void
    {
        $this->renderable(function (ValidationException $exception) {
            $request = request();

            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $errors = $exception->errors();
            $errorCode = 'ERR_INVALID_INPUT';

            if (isset($errors['email'])) {
                foreach ($errors['email'] as $message) {
                    if (str_contains($message, 'ERR_DUPLICATE_EMAIL')) {
                        $errorCode = 'ERR_DUPLICATE_EMAIL';
                        break;
                    }
                }
            }

            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'message' => 'فشل التحقق من صحة البيانات.',
                'errors' => $errors,
            ], 422);
        });

        $this->renderable(function (AuthenticationException $exception) {
            $request = request();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_UNAUTHENTICATED',
                    'message' => 'يرجى تسجيل الدخول.',
                ], 401);
            }

            return null;
        });

        $this->renderable(function (AuthorizationException $exception) {
            $request = request();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_PERMISSION',
                    'message' => $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'غير مصرح لك بتنفيذ هذا الإجراء.',
                ], 403);
            }

            return null;
        });

        $this->renderable(function (NotFoundHttpException $exception) {
            $request = request();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_NOT_FOUND',
                    'message' => 'المورد المطلوب غير موجود.',
                ], 404);
            }

            return null;
        });

        $this->renderable(function (BusinessException $exception) {
            $request = request();

            if ($request->expectsJson() || $request->is('api/*')) {
                $payload = [
                    'success' => false,
                    'error_code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                ];

                if ($exception->getContext() !== []) {
                    $payload['context'] = $exception->getContext();
                }

                return response()->json($payload, $exception->getStatusCode());
            }

            return null;
        });

        $this->renderable(function (Throwable $exception) {
            $request = request();

            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof NotFoundHttpException) {
                return null;
            }

            return response()->view('errors.500', [
                'exception' => $exception,
            ], 500);
        });

        $this->reportable(function (Throwable $exception) {
            // Keep Laravel default reporting pipeline active for observability.
        });
    }
}
