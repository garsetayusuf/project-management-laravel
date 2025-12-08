<?php

namespace App\Console\Commands;

use App\Models\RefreshToken;
use Illuminate\Console\Command;

class PruneExpiredRefreshTokens extends Command
{
    protected $signature = 'tokens:prune';
    protected $description = 'Prune expired and old revoked refresh tokens';

    public function handle(): int
    {
        $days = (int) config('jwt.prune_revoked_after_days', 30);
        $cutoffDate = now()->subDays($days);

        // Delete revoked tokens older than cutoff
        $revokedCount = RefreshToken::revoked()
            ->where('revoked_at', '<', $cutoffDate)
            ->delete();

        // Delete expired tokens
        $expiredCount = RefreshToken::expired()
            ->delete();

        $this->info("Pruned {$expiredCount} expired tokens");
        $this->info("Pruned {$revokedCount} old revoked tokens");

        return Command::SUCCESS;
    }
}
