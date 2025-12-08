<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TokenBlacklist extends Model
{
    protected $table = 'token_blacklist';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public static function isBlacklisted(string $token): bool
    {
        return self::where('token', $token)->exists();
    }

    public static function blacklist(string $token, Carbon|string $expiresAt): void
    {
        $userId = Auth::id();

        self::create([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
