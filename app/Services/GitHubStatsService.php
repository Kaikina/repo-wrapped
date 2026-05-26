<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RetryContributorStats;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class GitHubStatsService
{
    private const BASE_URI = 'https://api.github.com/';

    private const MAX_RETRIES = 5;

    private Client $client;

    public function __construct(
        private readonly RateLimitBucket $bucket,
        private readonly ?string $token = null,
    ) {
        $this->client = $this->buildClient($token);
    }

    public function withToken(?string $token): static
    {
        $clone = clone $this;
        $clone->client = $this->buildClient($token);

        return $clone;
    }

    // ------------------------------------------------------------------ //
    //  Public API
    // ------------------------------------------------------------------ //

    public function getRepoMeta(string $owner, string $repo): array
    {
        [$status, $data, $headers] = $this->getRaw("repos/{$owner}/{$repo}");

        if ($status !== 200) {
            Log::error('GitHubStatsService: Failed to fetch repo meta', ['owner' => $owner, 'repo' => $repo, 'status' => $status]);

            return [
                'stars' => 0,
                'forks' => 0,
                'description' => null,
                'default_branch' => 'main',
                'language' => null,
                'html_url' => null,
                'owner_avatar' => null,
            ];
        }

        return [
            'stars' => $data['stargazers_count'] ?? 0,
            'forks' => $data['forks_count'] ?? 0,
            'description' => $data['description'] ?? null,
            'default_branch' => $data['default_branch'] ?? 'main',
            'language' => $data['language'] ?? null,
            'html_url' => $data['html_url'] ?? null,
            'owner_avatar' => $data['owner']['avatar_url'] ?? null,
        ];
    }

    public function getFirstCommit(string $owner, string $repo, string $username, ?string $branch = null): ?string
    {
        // 1. Fetch with per_page=1 to get the Link header to find the last page
        [$status, $data, $headers] = $this->getRaw(
            "repos/{$owner}/{$repo}/commits",
            array_filter(['author' => $username, 'per_page' => 1, 'sha' => $branch])
        );

        if ($status !== 200 || empty($data) || !isset($data[0])) {
            return null;
        }

        // 2. Parse 'Link' header to find the last page
        $linkHeader = $headers['Link'][0] ?? $headers['link'][0] ?? '';
        if (preg_match('/<([^>]+)>;\s*rel="last"/', $linkHeader, $matches)) {
            $lastPageUrl = $matches[1];

            // Fetch the last page (it will only contain the very first commit because per_page=1)
            try {
                $response = $this->client->get($lastPageUrl);
                $oldestCommits = json_decode($response->getBody()->getContents(), true);

                if (!empty($oldestCommits) && isset($oldestCommits[0])) {
                    return $oldestCommits[0]['commit']['author']['date'] ?? null;
                }
            } catch (\Throwable) {
                // Fallback to the current result if last page fetch fails
            }
        }

        return $data[0]['commit']['author']['date'] ?? null;
    }

    public function getTotalCommits(string $owner, string $repo, string $username, ?string $branch = null): int
    {
        $total = 0;
        $page  = 1;

        do {
            [$status, $data, $headers] = $this->getRaw(
                "repos/{$owner}/{$repo}/commits",
                array_filter(['author' => $username, 'per_page' => 100, 'page' => $page, 'sha' => $branch])
            );

            if ($status !== 200 || !is_array($data)) {
                break;
            }

            $count  = count($data);
            $total += $count;
            $page++;
        } while ($count === 100);

        return $total;
    }

    public function getLanguages(string $owner, string $repo): array
    {
        [$status, $data, $headers] = $this->getRaw("repos/{$owner}/{$repo}/languages");

        if ($status !== 200 || ! is_array($data)) {
            return [];
        }

        $total = array_sum($data);

        if ($total === 0) {
            return [];
        }

        $result = [];
        arsort($data);

        foreach ($data as $lang => $bytes) {
            $result[] = [
                'name' => $lang,
                'bytes' => $bytes,
                'percentage' => round(($bytes / $total) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * Fetch contributor stats for a specific author.
     * Handles GitHub's 202 "computing" response with exponential backoff.
     * Returns ['lines_added', 'lines_deleted', 'lines_net', 'weekly_commits', 'partial' => bool]
     */
    public function getContributorStats(
        string $owner,
        string $repo,
        string $username,
        bool $dispatchRetryOnFailure = true,
    ): array {
        $delays = [1, 2, 4, 8, 16];
        $attempt = 0;
        $lastStatusCode = null;

        while ($attempt < self::MAX_RETRIES) {
            [$statusCode, $data, $headers] = $this->getRaw("repos/{$owner}/{$repo}/stats/contributors");
            $lastStatusCode = $statusCode;

            if ($statusCode === 200 && is_array($data)) {
                $stats = $this->extractContributorData($data, $username);

                // FALLBACK: If user has commits but wasn't in the top 100 contributors list,
                // calculate lines manually.
                if ($stats['lines_net'] === 0 && $stats['lines_added'] === 0) {
                   return $this->getManualLineStats($owner, $repo, $username);
                }

                return $stats;
            }

            if ($statusCode === 204) {
                // No content — repo might be empty or stats not available
                return $this->emptyContributorData();
            }

            if ($statusCode === 202) {
                Log::info('GitHubStatsService: 202 received for contributor stats, retrying', [
                    'owner' => $owner,
                    'repo' => $repo,
                    'attempt' => $attempt + 1,
                ]);

                sleep($delays[$attempt]);
                $attempt++;

                continue;
            }

            break;
        }

        // Only dispatch a background retry for genuine "GitHub still computing" (202).
        // Skip when the failure was our internal rate-limit bucket (429), a missing/private
        // repo (404), or any other terminal error — retrying won't change the outcome and
        // would just queue more failing jobs.
        if ($dispatchRetryOnFailure && $lastStatusCode === 202) {
            RetryContributorStats::dispatch($owner, $repo, $username)->onQueue('default');

            Log::warning('GitHubStatsService: Contributor stats still not ready, dispatched retry job', [
                'owner' => $owner, 'repo' => $repo,
            ]);
        } else {
            Log::warning('GitHubStatsService: Contributor stats unavailable, not retrying', [
                'owner' => $owner, 'repo' => $repo, 'status' => $lastStatusCode,
            ]);
        }

        return $this->emptyContributorData(true);
    }

    public function getUserAvatar(string $username): ?string
    {
        $data = $this->get("users/{$username}");

        return $data['avatar_url'] ?? null;
    }

    /**
     * Manual fallback for users NOT in the top 100 contributors.
     * Fetches individual commit details and sums additions/deletions.
     */
    private function getManualLineStats(string $owner, string $repo, string $username): array
    {
        // 1. Get recent commit SHAs for this author
        [$status, $commits, $headers] = $this->getRaw("repos/{$owner}/{$repo}/commits", [
            'author' => $username,
            'per_page' => 100, // Reasonable limit for manual calculation
        ]);

        if ($status !== 200 || ! is_array($commits)) {
            return $this->emptyContributorData();
        }

        $added = 0;
        $deleted = 0;
        $weeklyBuckets = [];

        // 2. Fetch each commit to get stats (additions/deletions)
        foreach ($commits as $commit) {
            $sha = $commit['sha'] ?? null;
            $date = $commit['commit']['author']['date'] ?? null;
            if (! $sha || ! $date) {
                continue;
            }

            // Group by Year-Week for the sparkline trend
            $weekKey = date('Y-W', strtotime($date));
            $weeklyBuckets[$weekKey] = ($weeklyBuckets[$weekKey] ?? 0) + 1;

            [$s, $details, $h] = $this->getRaw("repos/{$owner}/{$repo}/commits/{$sha}");

            if ($s === 200 && isset($details['stats'])) {
                $added += (int) ($details['stats']['additions'] ?? 0);
                $deleted += (int) ($details['stats']['deletions'] ?? 0);
            }
        }

        // Sort buckets chronologically and extract counts for the sparkline
        ksort($weeklyBuckets);
        $counts = array_values($weeklyBuckets);

        return [
            'lines_added' => $added,
            'lines_deleted' => $deleted,
            'lines_net' => $added,
            'weekly_commits' => $counts,
            'partial' => count($commits) >= 100,
        ];
    }

    private function emptyContributorData(bool $partial = false): array
    {
        return [
            'lines_added' => 0,
            'lines_deleted' => 0,
            'lines_net' => 0,
            'weekly_commits' => [],
            'partial' => $partial,
        ];
    }

    // ------------------------------------------------------------------ //
    //  Private helpers
    // ------------------------------------------------------------------ //

    private function extractContributorData(array $contributors, string $username): array
    {
        $linesAdded = 0;
        $linesDeleted = 0;
        $weeklyBuckets = [];

        foreach ($contributors as $contributor) {
            $login = $contributor['author']['login'] ?? '';

            if (strtolower($login) !== strtolower($username)) {
                continue;
            }

            foreach ($contributor['weeks'] ?? [] as $week) {
                $linesAdded += (int) ($week['a'] ?? 0);
                $linesDeleted += (int) ($week['d'] ?? 0);
                $weeklyBuckets[] = (int) ($week['c'] ?? 0);
            }

            break;
        }

        return [
            'lines_added' => $linesAdded,
            'lines_deleted' => $linesDeleted,
            'lines_net' => $linesAdded - $linesDeleted,
            'weekly_commits' => $weeklyBuckets,
            'partial' => false,
        ];
    }

    private function get(string $endpoint, array $query = []): array
    {
        [, $data, $headers] = $this->getRaw($endpoint, $query);

        return is_array($data) ? $data : [];
    }

    private function getRaw(string $endpoint, array $query = []): array
    {
        if (! $this->bucket->acquire()) {
            Log::warning('GitHubStatsService: Rate limit bucket exhausted, skipping request');

            return [429, [], []];
        }

        try {
            $response = $this->client->get($endpoint, ['query' => $query]);

            $remaining = (int) ($response->getHeader('x-ratelimit-remaining')[0] ?? 9999);
            Log::info('GitHub API request', [
                'endpoint' => $endpoint,
                'remaining' => $remaining,
            ]);

            if ($remaining < 100) {
                Log::warning('GitHub rate limit critically low', ['remaining' => $remaining]);
            }

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);
            $headers = $response->getHeaders();

            return [$statusCode, $body, $headers];
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            Log::error('GitHub API client error', [
                'endpoint' => $endpoint,
                'status' => $statusCode,
                'message' => $e->getMessage(),
            ]);

            return [$statusCode, [], $e->getResponse()->getHeaders()];
        } catch (RequestException $e) {
            Log::error('GitHub API request error', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return [500, [], []];
        }
    }

    private function buildClient(?string $token): Client
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'RepoWrapped/1.0',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $resolvedToken = $token ?? config('services.github.fallback_token');

        if ($resolvedToken) {
            $headers['Authorization'] = "Bearer {$resolvedToken}";
        }

        return new Client([
            'base_uri' => self::BASE_URI,
            'headers' => $headers,
            'timeout' => 20,
            'http_errors' => false,
        ]);
    }
}
