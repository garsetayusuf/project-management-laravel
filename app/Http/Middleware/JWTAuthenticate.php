<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JWTAuthenticate
{
    protected JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken(request: $request);

        if (! $token) {
            return response()->json(data: [
                'status' => 'error',
                'data' => null,
                'message' => 'Token missing, invalid, or expired',
                'error' => 'Unauthorized',
                'statusCode' => 401,
            ], status: 401);
        }

        $user = $this->jwtService->getUserFromToken(token: $token);

        if (! $user) {
            return response()->json(data: [
                'status' => 'error',
                'data' => null,
                'message' => 'Token missing, invalid, or expired',
                'error' => 'Unauthorized',
                'statusCode' => 401,
            ], status: 401);
        }

        if ($this->jwtService->isAccessTokenBlacklisted($token)) {
            return response()->json(data: [
                'status' => 'error',
                'data' => null,
                'message' => 'Token missing, invalid, or expired',
                'error' => 'Unauthorized',
                'statusCode' => 401,
            ], status: 401);
        }

        $request->setUserResolver(callback: fn () => $user);
        Auth::setUser($user);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header(key: 'Authorization');

        if (! $header) {
            return null;
        }

        // Expected format: "Bearer <token>"
        if (! str_starts_with(haystack: $header, needle: 'Bearer ')) {
            return null;
        }

        return substr(string: $header, offset: 7);
    }
}
