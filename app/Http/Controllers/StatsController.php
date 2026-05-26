<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ComputeRepoStats;
use App\Services\StatsCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsCacheService $cache,
    ) {}

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'owner' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\-\.]+$/'],
            'repo'  => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\-\._]+$/'],
        ]);

        $owner    = strtolower(trim($request->string('owner')->toString()));
        $repo     = strtolower(trim($request->string('repo')->toString()));
        $username = strtolower(auth()->user()->github_username);

        return redirect()->route('stats.show', compact('owner', 'repo', 'username'));
    }

    public function show(string $owner, string $repo, string $username): View|RedirectResponse
    {
        $canonical = [strtolower($owner), strtolower($repo), strtolower($username)];
        if ([$owner, $repo, $username] !== $canonical) {
            [$owner, $repo, $username] = $canonical;
            return redirect()->route('stats.show', compact('owner', 'repo', 'username'), 301);
        }

        $key = "stats:{$owner}:{$repo}:{$username}";
        
        // Layer 1: Check Redis directly (fast path)
        try {
            if (\Illuminate\Support\Facades\Redis::exists($key)) {
                [$stats, $status] = $this->cache->resolve($owner, $repo, $username);
                return view('pages.stats', compact('stats', 'status', 'owner', 'repo', 'username'));
            }
        } catch (\Throwable) {
            // Degrade to Postgres
        }

        // Layer 2: Check Postgres
        $record = \App\Models\RepoStat::where(compact('owner', 'repo', 'username'))->first();

        if ($record && !$record->isExpired()) {
            [$stats, $status] = $this->cache->resolve($owner, $repo, $username);
            return view('pages.stats', compact('stats', 'status', 'owner', 'repo', 'username'));
        }

        // Needs sync or background compute — show loading screen
        if (!$record || $record->isExpired()) {
            ComputeRepoStats::dispatch($owner, $repo, $username)->onQueue('default');
        }

        return view('pages.loading', compact('owner', 'repo', 'username'));
    }

    public function status(string $owner, string $repo, string $username): \Illuminate\Http\JsonResponse
    {
        $owner = strtolower($owner);
        $repo = strtolower($repo);
        $username = strtolower($username);

        $key = "stats:{$owner}:{$repo}:{$username}";
        
        try {
            if (\Illuminate\Support\Facades\Redis::exists($key)) {
                return response()->json(['status' => 'fresh']);
            }
        } catch (\Throwable) {}

        $record = \App\Models\RepoStat::where(compact('owner', 'repo', 'username'))->first();

        if (!$record) {
            return response()->json(['status' => 'missing']);
        }

        return response()->json([
            'status' => $record->isExpired() ? 'expired' : ($record->isStale() ? 'stale' : 'fresh')
        ]);
    }
}
