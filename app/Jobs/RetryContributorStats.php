<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RepoStat;
use App\Services\GitHubStatsService;
use App\Services\RateLimitBucket;
use App\Services\StatsCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryContributorStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 30;

    public function __construct(
        public readonly string $owner,
        public readonly string $repo,
        public readonly string $username,
    ) {}

    public function handle(GitHubStatsService $github, StatsCacheService $cache): void
    {
        Log::info('RetryContributorStats: polling', [
            'owner' => $this->owner, 'repo' => $this->repo,
        ]);

        $user          = \App\Models\User::where('github_username', $this->username)->first();
        $github        = $github->withToken($user?->github_token);
        $contributorStats = $github->getContributorStats(
            $this->owner,
            $this->repo,
            $this->username,
            dispatchRetryOnFailure: false,
        );

        if ($contributorStats['partial'] === true) {
            Log::warning('RetryContributorStats: still partial, will retry via job backoff', [
                'owner' => $this->owner, 'attempt' => $this->attempts(),
            ]);
            throw new \RuntimeException('Contributor stats still partial after sync retries');
        }

        // Merge contributor data into existing stats record
        $record = RepoStat::where('owner', $this->owner)
            ->where('repo', $this->repo)
            ->where('username', $this->username)
            ->first();

        if ($record !== null) {
            $merged = array_merge($record->stats, $contributorStats);
            $cache->persist($this->owner, $this->repo, $this->username, $merged);
        }

        Log::info('RetryContributorStats: success', ['owner' => $this->owner]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetryContributorStats: permanently failed', [
            'owner'   => $this->owner,
            'repo'    => $this->repo,
            'message' => $exception->getMessage(),
        ]);
    }
}
