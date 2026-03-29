<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'smtp_username',
        'smtp_password',
    ];

    public function render($request, Throwable $exception): Response
    {
        $preparedException = $this->prepareException($exception);
        $isBrowserRequest = ! $request->expectsJson() && ! $request->is('api/*');
        $isHandledClientException = $preparedException instanceof ValidationException
            || $preparedException instanceof AuthenticationException
            || $preparedException instanceof AuthorizationException
            || $preparedException instanceof NotFoundHttpException
            || ($preparedException instanceof HttpExceptionInterface
                && in_array((int) $preparedException->getStatusCode(), [403, 404], true));

        if ($isBrowserRequest && $this->shouldRenderExternalPortalError($request, $preparedException)) {
            return $this->renderExternalPortalError($request, $preparedException);
        }

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

    private function shouldRenderExternalPortalError($request, Throwable $exception): bool
    {
        if (! $this->isExternalPortalRequest($request)) {
            return false;
        }

        $statusCode = $this->resolvePortalErrorStatusCode($exception);

        return in_array($statusCode, [403, 404], true);
    }

    private function renderExternalPortalError($request, Throwable $exception): Response
    {
        $statusCode = $this->resolvePortalErrorStatusCode($exception);
        $portal = $this->resolveExternalPortal($request);
        $primaryRoute = $portal === 'b2c' ? 'b2c.dashboard' : 'b2b.dashboard';
        $secondaryRoute = $portal === 'b2c' ? 'b2c.shipments.index' : 'b2b.shipments.index';

        return response()->view('pages.browser-guidance', [
            'title' => __('portal_shipments.errors.external.' . $statusCode . '.heading'),
            'eyebrow' => __('portal_shipments.errors.external.' . $statusCode . '.eyebrow'),
            'heading' => __('portal_shipments.errors.external.' . $statusCode . '.heading'),
            'message' => __('portal_shipments.errors.external.' . $statusCode . '.message'),
            'statusCode' => $statusCode,
            'primaryActionUrl' => Route::has($primaryRoute) ? route($primaryRoute) : url('/'),
            'primaryActionLabel' => __('portal_shipments.errors.external.primary_action'),
            'secondaryActionUrl' => Route::has($secondaryRoute) ? route($secondaryRoute) : url('/'),
            'secondaryActionLabel' => __('portal_shipments.errors.external.secondary_action'),
        ], $statusCode);
    }

    private function isExternalPortalRequest($request): bool
    {
        return $request->is('b2c/*')
            || $request->is('b2b/*')
            || ($request->is('notifications') && (string) optional($request->user()?->account)->type !== 'internal');
    }

    private function resolveExternalPortal($request): string
    {
        if ($request->is('b2c/*')) {
            return 'b2c';
        }

        if ($request->is('notifications')) {
            return (string) optional($request->user()?->account)->type === 'individual' ? 'b2c' : 'b2b';
        }

        return 'b2b';
    }

    private function resolvePortalErrorStatusCode(Throwable $exception): int
    {
        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        if ($exception instanceof NotFoundHttpException) {
            return 404;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return (int) $exception->getStatusCode();
        }

        return 500;
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
