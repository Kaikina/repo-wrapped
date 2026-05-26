<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StatsCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class CardController extends Controller
{
    private const VALID_THEMES = ['light', 'dark', 'radical', 'merko'];

    public function __construct(
        private readonly StatsCacheService $cache,
    ) {}

    public function show(string $owner, string $repo, string $username): Response|RedirectResponse
    {
        $canonical = [strtolower($owner), strtolower($repo), strtolower($username)];
        if ([$owner, $repo, $username] !== $canonical) {
            [$owner, $repo, $username] = $canonical;
            return redirect()->route('card.show', compact('owner', 'repo', 'username'), 301);
        }

        [$stats] = $this->cache->resolve($owner, $repo, $username);

        $theme  = $this->resolveTheme(request()->query('theme', 'light'));
        $hide   = $this->resolveHide(request()->query('hide', ''));
        $avatar = $this->fetchAvatarAsBase64($stats['avatar_url'] ?? null);

        $svg = view('card', compact('stats', 'theme', 'hide', 'avatar', 'owner', 'repo', 'username'))->render();

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'no-cache, max-age=0, s-maxage=3600',
            'Pragma'        => 'no-cache',
        ]);
    }

    private function resolveTheme(mixed $theme): string
    {
        return in_array($theme, self::VALID_THEMES, strict: true) ? $theme : 'light';
    }

    private function resolveHide(mixed $hide): array
    {
        if (empty($hide)) {
            return [];
        }

        return array_map('trim', explode(',', (string) $hide));
    }

    private function fetchAvatarAsBase64(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $mime     = $response->header('Content-Type') ?? 'image/png';
                $mime     = explode(';', $mime)[0];
                $base64   = base64_encode($response->body());
                return "data:{$mime};base64,{$base64}";
            }
        } catch (\Throwable) {
            // Silently degrade — card renders without avatar
        }

        return null;
    }
}
