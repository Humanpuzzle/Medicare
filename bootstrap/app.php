<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Map business rule exceptions to HTTP responses
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();

            // Business conflict errors → 409
            if (str_contains(strtolower($message), 'overlap') ||
                str_contains(strtolower($message), 'conflict') ||
                str_contains(strtolower($message), 'already has an appointment') ||
                str_contains(strtolower($message), 'cannot transition') ||
                str_contains(strtolower($message), 'cannot cancel') ||
                str_contains(strtolower($message), 'at least 24 hours')) {
                return response()->json([
                    'message' => $message,
                ], 409);
            }

            // Validation-like errors → 422
            if (str_contains(strtolower($message), 'must be') ||
                str_contains(strtolower($message), 'invalid') ||
                str_contains(strtolower($message), 'minimum') ||
                str_contains(strtolower($message), '15-minute') ||
                str_contains(strtolower($message), 'duration') ||
                str_contains(strtolower($message), 'contained') ||
                str_contains(strtolower($message), 'multiple') ||
                str_contains(strtolower($message), 'future') ||
                str_contains(strtolower($message), 'start time must be before')) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        });
    })->create();
