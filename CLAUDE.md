# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

**RepoWrapped** — a "Spotify Wrapped"-style stats page for a GitHub repo + a contributor. Users sign in with GitHub, enter `owner/repo`, and get a public, shareable stats page at `/u/{owner}/{repo}/{username}`. There's also an SVG card endpoint at `/card/{owner}/{repo}/{username}` for embeds (think shields.io-style README badges, with `?theme=` and `?hide=` query params).

Stack: Laravel 13 / PHP 8.3, Postgres, Redis, Vite + Tailwind 4, Laravel Socialite (GitHub OAuth), Guzzle for GitHub API.

## Common commands

Local dev (host, not docker):
```bash
composer setup        # composer install, .env, key:generate, migrate, npm install, npm build
composer dev          # runs server + queue:listen + pail + vite concurrently
composer test         # config:clear + artisan test
php artisan test --filter=SomeTest   # single test
./vendor/bin/pint     # code style (Laravel Pint)
```

Docker dev (the intended setup — see `docker-compose.yml`):
```bash
docker-compose up -d                    # app (php-fpm), nginx (port 8080), postgres (5434), redis (6379), queue worker
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Queue worker is a separate container (`repowrapped_queue`) running `queue:work redis`. If you change job code, restart it.

## Architecture

### Request flow for stats

The hot path is `StatsController::show` → `StatsCacheService::resolve`:

1. **Redis** (`stats:{owner}:{repo}:{username}`, 1h TTL) — fast path.
2. **Postgres** (`repo_stats` table) — `isStale()` ≥ 1h, `isExpired()` ≥ 24h.
3. **Miss** — controller dispatches `ComputeRepoStats` job and renders `pages.loading`, which polls `/u/.../status` (JSON) until status is `fresh`.

Note: `StatsCacheService::resolve` currently has its Postgres staleness branch commented out (lines 46–66). When Redis misses, it recomputes synchronously regardless of whether a Postgres record exists. The controller does its own Postgres check first to avoid that. If you're changing cache behavior, read both files together — they're coupled.

### GitHub API layer

`GitHubStatsService` (`app/Services/GitHubStatsService.php`) wraps Guzzle. Key behaviors that aren't obvious from the surface:

- **Per-request token**: `withToken()` returns a clone with a different bearer token. `StatsCacheService::resolveGithubService()` uses the requested user's stored OAuth token when available, falling back to `GITHUB_FALLBACK_TOKEN` env var (set in `config/services.php`).
- **Rate limiter** (`RateLimitBucket`): Redis token bucket, 10 req/sec + 4500 req/hour. If Redis is down, it **bypasses** the limit (logs a warning) — graceful degradation, not fail-closed.
- **Contributor stats 202 handling**: GitHub returns 202 while it computes stats. The service retries with exponential backoff (1,2,4,8,16s, MAX_RETRIES=5). If still 202, it dispatches `RetryContributorStats` (5 tries, 30s backoff) and returns partial data with `partial: true`. The retry job merges results back into the existing `RepoStat.stats` JSON column when ready.
- **Top-100 fallback**: GitHub's contributor stats endpoint only returns the top 100 contributors. If the requested user isn't in that list, `getManualLineStats()` walks individual commits to sum additions/deletions — N+1 by design, capped at 100 commits.
- **First commit**: uses `per_page=1` + parses the `Link: rel="last"` header to jump to the oldest commit page without paginating the whole history.

### Auth

`AuthController` uses Socialite (`read:user` scope). The OAuth access token is stored on `User.github_token` with the `encrypted` cast — every read decrypts. Don't query it raw; use the model.

### SVG card endpoint

`CardController::show` renders `resources/views/card.blade.php` as `image/svg+xml`. Avatars are fetched server-side and base64-inlined so the SVG is self-contained for use as `<img src>`. Themes are gated by `VALID_THEMES`; unknown values fall back to `light`.

### Frontend

Vite + Tailwind 4. Blade views only (no SPA). Pages: `home`, `loading` (polls `/status`), `stats`, `card` (SVG template, not a normal page).

## Testing

PHPUnit 12. `phpunit.xml` overrides env for tests: SQLite `:memory:`, sync queue, array cache/session. So tests don't need Redis/Postgres running — but anything depending on real Redis behavior (e.g. `RateLimitBucket`) needs to be mocked or designed around the array cache.

## Gotchas

- `.env.example` contains a real-looking `GITHUB_FALLBACK_TOKEN` value. Treat it as compromised and rotate before any public deployment — don't copy it into a real `.env`.
- `RepoStat.stats` is a JSON-cast array column. Reading/writing replaces the whole blob; partial updates need an array_merge + save (see `RetryContributorStats::handle`).
- Lowercase normalization: `StatsController` lowercases `owner`/`repo` but **not** `username`. The cache key and DB row use the values as-passed. If a route is hit with different casing for `username`, you get cache misses and duplicate rows.
- The queue worker container in docker-compose runs `queue:work` (not `queue:listen`), so it won't pick up code changes without a restart.
