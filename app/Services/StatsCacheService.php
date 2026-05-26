<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ComputeRepoStats;
use App\Models\RepoStat;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StatsCacheService
{
    private const REDIS_TTL = 3600;   // 1 hour

    private const STALE_HOURS = 1;

    private const EXPIRE_HOURS = 24;

    public function __construct(
        private readonly GitHubStatsService $github,
    ) {}

    /**
     * Resolve stats using two-layer cache strategy.
     * Returns [stats_array, status_string]
     * Status: 'fresh' | 'stale' | 'expired' | 'missing'
     */
    public function resolve(string $owner, string $repo, string $username): array
    {
        [$owner, $repo, $username] = $this->normalize($owner, $repo, $username);
        $key = $this->cacheKey($owner, $repo, $username);

        // Layer 1: Redis (< 1 hour old)
        $redisData = $this->getFromRedis($key);
        if ($redisData !== null) {
            return [$redisData, 'fresh'];
        }

        // Layer 2: Postgres
        $record = RepoStat::where('owner', $owner)
            ->where('repo', $repo)
            ->where('username', $username)
            ->first();

//        if ($record !== null) {
//            if ($record->isExpired()) {
//                // > 24h — recompute synchronously
//                $stats = $this->computeAndStore($owner, $repo, $username);
//
//                return [$stats, 'expired'];
//            }
//
//            if ($record->isStale()) {
//                // 1–24h — serve stale, dispatch background recompute
//                ComputeRepoStats::dispatch($owner, $repo, $username)->onQueue('default');
//                $this->storeInRedis($key, $record->stats);
//
//                return [$record->stats, 'stale'];
//            }
//
//            // < 1h but not in Redis (e.g., Redis flushed) — restore Redis cache
//            $this->storeInRedis($key, $record->stats);
//
//            return [$record->stats, 'fresh'];
//        }

        // No cache at all — synchronous compute
        $stats = $this->computeAndStore($owner, $repo, $username);

        return [$stats, 'missing'];
    }

    /**
     * Compute stats, write to both Redis and Postgres.
     */
    public function computeAndStore(string $owner, string $repo, string $username): array
    {
        [$owner, $repo, $username] = $this->normalize($owner, $repo, $username);
        $githubService = $this->resolveGithubService($username);

        $repoMeta         = $githubService->getRepoMeta($owner, $repo);
        $defaultBranch    = $repoMeta['default_branch'] ?? null;
        $firstCommit      = $githubService->getFirstCommit($owner, $repo, $username, $defaultBranch);
        $totalCommits     = $githubService->getTotalCommits($owner, $repo, $username, $defaultBranch);
        $languages        = $githubService->getLanguages($owner, $repo);
        $contributorStats = $githubService->getContributorStats($owner, $repo, $username);

        $avatarUrl = optional(User::where('github_username', $username)->first())->avatar_url;

        if (! $avatarUrl) {
            $avatarUrl = $githubService->getUserAvatar($username) ?? ($repoMeta['owner_avatar'] ?? null);
        }

        $stats = array_merge($repoMeta, [
            'first_commit' => $firstCommit,
            'total_commits' => $totalCommits,
            'languages' => $languages,
            'avatar_url' => $avatarUrl,
            'owner' => $owner,
            'repo' => $repo,
            'username' => $username,
        ], $contributorStats);

        $this->persist($owner, $repo, $username, $stats);

        return $stats;
    }

    public function persist(string $owner, string $repo, string $username, array $stats): void
    {
        [$owner, $repo, $username] = $this->normalize($owner, $repo, $username);
        $key = $this->cacheKey($owner, $repo, $username);

        // Write to Postgres
        RepoStat::updateOrCreate(
            compact('owner', 'repo', 'username'),
            ['stats' => $stats, 'computed_at' => now()]
        );

        // Write to Redis
        $this->storeInRedis($key, $stats);
    }

    // ------------------------------------------------------------------ //
    //  Redis helpers — graceful degradation
    // ------------------------------------------------------------------ //

    private function getFromRedis(string $key): ?array
    {
        try {
            $value = Redis::get($key);

            return $value ? json_decode($value, true) : null;
        } catch (\Throwable $e) {
            Log::warning('StatsCacheService: Redis unavailable for read', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function storeInRedis(string $key, array $stats): void
    {
        try {
            Redis::setex($key, self::REDIS_TTL, json_encode($stats));
        } catch (\Throwable $e) {
            Log::warning('StatsCacheService: Redis unavailable for write', ['error' => $e->getMessage()]);
        }
    }

    private function cacheKey(string $owner, string $repo, string $username): string
    {
        return "stats:{$owner}:{$repo}:{$username}";
    }

    /**
     * GitHub treats owner, repo, and username case-insensitively, so normalize at the
     * service boundary to keep one cache key + one DB row per identity.
     */
    private function normalize(string $owner, string $repo, string $username): array
    {
        return [strtolower($owner), strtolower($repo), strtolower($username)];
    }

    private function resolveGithubService(string $username): GitHubStatsService
    {
        $user = User::where('github_username', $username)->first();

        return $this->github->withToken($user?->github_token);
    }
}
