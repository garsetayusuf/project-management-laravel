<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class JWTService
{
    public function generateAccessToken(User $user): string
    {
        $now = time();
        $ttl = (int) config('jwt.access_ttl', 15);
        $exp = $now + ($ttl * 60);

        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'iat' => $now,
            'exp' => $exp,
            'type' => 'access',
        ];

        return JWT::encode($payload, config('jwt.secret'), config('jwt.algo', 'HS256'));
    }

    public function generateRefreshToken(User $user, ?Request $request = null): array
    {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $expiresAt = now()->addMinutes((int) config('jwt.refresh_ttl', 60 * 24 * 30));

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'device_name' => $this->parseUserAgent($request?->userAgent()),
            'ip_address' => $request?->ip(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'model' => $refreshToken,
        ];
    }

    /**
     * Validate refresh token from database
     */
    public function validateRefreshToken(string $token): ?RefreshToken
    {
        $tokenHash = hash('sha256', $token);

        return RefreshToken::where('token_hash', $tokenHash)
            ->active()
            ->first();
    }

    /**
     * Validate and decode a JWT access token
     */
    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(config('jwt.secret'), config('jwt.algo', 'HS256')));
        } catch (ExpiredException $e) {
            // Token has expired
            return null;
        } catch (SignatureInvalidException $e) {
            // Invalid signature
            return null;
        } catch (BeforeValidException $e) {
            // Token used before valid
            return null;
        } catch (Throwable $e) {
            // Any other JWT error
            return null;
        }
    }

    /**
     * Check if access token is blacklisted (revoked)
     */
    public function isAccessTokenBlacklisted(string $token): bool
    {
        return \App\Models\TokenBlacklist::isBlacklisted($token);
    }

    /**
     * Get a user from a JWT token
     */
    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->validateToken($token);

        if (! $payload || ! isset($payload->user_id)) {
            return null;
        }

        return User::find($payload->user_id);
    }

    /**
     * Parse user agent to get device name
     */
    protected function parseUserAgent(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return 'Unknown Device';
        }

        if (str_contains($userAgent, 'Mobile')) {
            return 'Mobile Device';
        }
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome Browser';
        }
        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox Browser';
        }
        if (str_contains($userAgent, 'Safari')) {
            return 'Safari Browser';
        }

        return 'Desktop Browser';
    }
}
