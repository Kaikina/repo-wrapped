# RepoWrapped

A "Spotify Wrapped"-style stats page for a GitHub repository and one of its
contributors. Sign in with GitHub, enter `owner/repo`, and share a public stats
page — or drop an SVG card into your README.

**Live app:** <https://repo-wrapped.tom-girou.dev>

## Features

- Public stats page at `/u/{owner}/{repo}/{username}` — commits, additions/deletions,
  weekly activity, first commit date, ranking among contributors.
- Embeddable SVG card at `/card/{owner}/{repo}/{username}` with `?theme=` and
  `?hide=` query params (shields.io-style, self-contained — avatars are
  base64-inlined).
- GitHub OAuth sign-in (Laravel Socialite, `read:user` scope). The user's token
  is used for API calls when available; otherwise a fallback token is used.
- Background computation via a Redis-backed queue; results cached in Redis (1h)
  and Postgres (24h).
- Handles GitHub's 202 "stats are being computed" responses with retries and a
  manual fallback for contributors outside the top-100.

## Stack

Laravel 13 · PHP 8.3 · Postgres · Redis · Vite + Tailwind 4 · Guzzle · Laravel
Socialite.

## Run it locally

### Docker (recommended)

```bash
cp .env.example .env
# fill in GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET, GITHUB_FALLBACK_TOKEN
docker-compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

App is served at <http://localhost:8080>. A separate `repowrapped_queue`
container runs `queue:work` for background stats jobs.

### On the host

```bash
composer setup    # install, .env, key:generate, migrate, npm install + build
composer dev      # serve + queue + pail + vite
composer test
```

## Embedding the SVG card

```markdown
![RepoWrapped](https://repo-wrapped.tom-girou.dev/card/laravel/framework/taylorotwell?theme=dark)
```

Query params:
- `theme` — `light` (default) or `dark`.
- `hide` — comma-separated list of sections to omit.

## GitHub OAuth setup

Create a GitHub OAuth app and set:
- Authorization callback URL: `http://localhost:8080/auth/github/callback`
  (or your domain in production).
- `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` in `.env`.
- `GITHUB_FALLBACK_TOKEN` — a personal access token used when a visitor has no
  OAuth token of their own (e.g. for the SVG card endpoint).

## License

MIT.
