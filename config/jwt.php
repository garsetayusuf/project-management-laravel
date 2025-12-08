<?php

return [
    /**
     * JWT Secret Key
     * Used to sign and verify JWT tokens
     */
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    /**
     * Access Token Time To Live (TTL) in minutes
     * Default: 15 minutes
     */
    'access_ttl' => env('JWT_ACCESS_TTL', 15),

    /**
     * Refresh Token Time To Live (TTL) in minutes
     * Default: 30 days (43200 minutes)
     */
    'refresh_ttl' => env('JWT_REFRESH_TTL', 60 * 24 * 30),

    /**
     * Algorithm used to sign JWT tokens
     * Supported: HS256, HS512, RS256, RS512, etc.
     */
    'algo' => env('JWT_ALGO', 'HS256'),

    /**
     * Whether to rotate refresh tokens on each refresh
     * Default: true (recommended for security)
     */
    'rotate_refresh_tokens' => env('JWT_ROTATE_REFRESH_TOKENS', true),

    /**
     * Number of days to keep revoked tokens before pruning
     * Default: 90 days
     */
    'prune_revoked_after_days' => env('JWT_PRUNE_REVOKED_AFTER_DAYS', 90),
];
