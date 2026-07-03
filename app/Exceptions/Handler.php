<?php

namespace App\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            \Log::error('Exception occurred', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Only handle API requests
        if (!$request->expectsJson() && !$request->is('api/*')) {
            return parent::render($request, $e);
        }

        // Proxmox connection failures
        if ($this->isProxmoxException($e)) {
            return ApiResponse::error(
                'Unable to connect to the virtualization platform. Please try again later.',
                502
            );
        }

        // Payment failures
        if ($this->isPaymentException($e)) {
            return ApiResponse::error(
                'Payment processing failed. Please try again or contact support.',
                402
            );
        }

        // Insufficient balance
        if ($this->isInsufficientBalanceException($e)) {
            return ApiResponse::error(
                'Insufficient balance. Please recharge your account.',
                402
            );
        }

        // Authentication
        if ($e instanceof AuthenticationException) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        // Validation
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Not found
        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error('Resource not found.', 404);
        }

        // Method not allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error('Method not allowed.', 405);
        }

        return parent::render($request, $e);
    }

    protected function isProxmoxException(Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Proxmox') ||
               str_contains($message, 'cURL error 7') ||
               str_contains($message, 'Connection refused') ||
               str_contains($message, 'Could not resolve host');
    }

    protected function isPaymentException(Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Payment') ||
               str_contains($message, 'Epay');
    }

    protected function isInsufficientBalanceException(Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Insufficient') ||
               str_contains($message, 'Balance');
    }
}
