<?php

use App\Http\Middleware\JWTAuthenticate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => JWTAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(data: [
                    'status' => 'error',
                    'data' => ['errors' => $e->errors()],
                    'message' => 'Validation failed',
                    'error' => 'ValidationError',
                    'statusCode' => 422,
                ], status: 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(data: [
                    'status' => 'error',
                    'data' => null,
                    'message' => 'Token missing, invalid, or expired',
                    'error' => 'Unauthorized',
                    'statusCode' => 401,
                ], status: 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(data: [
                    'status' => 'error',
                    'data' => null,
                    'message' => 'You do not have permission to perform this action',
                    'error' => 'Forbidden',
                    'statusCode' => 403,
                ], status: 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(data: [
                    'status' => 'error',
                    'data' => null,
                    'message' => 'Resource not found',
                    'error' => 'NotFound',
                    'statusCode' => 404,
                ], status: 404);
            }
        });
    })->create();
