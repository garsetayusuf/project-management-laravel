<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'ip_address',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this refresh token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only active tokens.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Scope to get only expired tokens.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get only revoked tokens.
     */
    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * Revoke this token.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Check if token is valid.
     */
    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at > now();
    }

    /**
     * Update last used timestamp.
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
