<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\StatsCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComputeRepoStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $owner,
        public readonly string $repo,
        public readonly string $username,
    ) {}

    public function handle(StatsCacheService $cache): void
    {
        Log::info('ComputeRepoStats: starting', [
            'owner' => $this->owner, 'repo' => $this->repo, 'username' => $this->username,
        ]);

        $stats = $cache->computeAndStore($this->owner, $this->repo, $this->username);

        Log::info('ComputeRepoStats: done', [
            'owner'         => $this->owner,
            'total_commits' => $stats['total_commits'] ?? null,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ComputeRepoStats: failed', [
            'owner'   => $this->owner,
            'repo'    => $this->repo,
            'message' => $exception->getMessage(),
        ]);
    }
}
