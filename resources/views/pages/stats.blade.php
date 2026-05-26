@extends('layouts.app')

@section('title', "{$username}'s stats on {$owner}/{$repo}")
@section('meta_description', "GitHub contribution stats for {$username} on {$owner}/{$repo} — commits, lines written, languages, and more.")

@push('head')
{{-- Open Graph --}}
<meta property="og:type"        content="website">
<meta property="og:title"       content="{{ "{$username}'s stats on {$owner}/{$repo}" }}">
<meta property="og:description" content="{{ "Total commits: " . ($stats['total_commits'] ?? '?') . " · Lines net: " . number_format($stats['lines_net'] ?? 0) }}">
<meta property="og:image"       content="{{ route('card.show', compact('owner', 'repo', 'username')) }}?theme=dark">
<meta property="og:url"         content="{{ request()->url() }}">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:image"      content="{{ route('card.show', compact('owner', 'repo', 'username')) }}?theme=dark">
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    {{-- Header --}}
    <div class="mb-10 flex flex-col sm:flex-row items-start sm:items-center gap-6">
        @if(!empty($stats['avatar_url']))
            <img src="{{ $stats['avatar_url'] }}" alt="{{ $username }}"
                 class="w-20 h-20 rounded-full ring-4 ring-violet-500/30 shadow-2xl shadow-violet-500/20">
        @endif
        <div>
            <div class="flex items-center gap-3 mb-1 flex-wrap">
                <h1 class="text-3xl font-extrabold">{{ $username }}</h1>
                @if($status === 'stale')
                    <span class="inline-flex items-center gap-1.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs px-3 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        Refreshing in background
                    </span>
                @elseif(!empty($stats['partial']))
                    <span class="inline-flex items-center gap-1.5 bg-sky-500/10 border border-sky-500/30 text-sky-400 text-xs px-3 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                        Line stats computing…
                    </span>
                @endif
            </div>
            <p class="text-white/50 text-lg">
                Contribution stats on
                <a href="https://github.com/{{ $owner }}/{{ $repo }}" target="_blank" rel="noopener"
                   class="text-violet-400 hover:text-violet-300 transition-colors font-semibold">
                    {{ $owner }}/{{ $repo }}
                </a>
            </p>
        </div>
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach([
            ['Total Commits', number_format($stats['total_commits'] ?? 0), '🔥', 'violet'],
            ['Lines Written', number_format($stats['lines_net'] ?? 0), '📝', 'cyan'],
            ['Lines Deleted', '-' . number_format($stats['lines_deleted'] ?? 0), '➖', 'rose'],
        ] as [$label, $value, $icon, $color])
        <div class="bg-card-bg/80 border border-white/10 rounded-2xl p-5 hover:border-{{$color}}-500/30 transition-all duration-300 group">
            <div class="text-2xl mb-2">{{ $icon }}</div>
            <div class="text-2xl font-extrabold text-white mb-1">{{ $value }}</div>
            <div class="text-white/40 text-xs uppercase tracking-wider">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    {{-- Repo meta + first commit --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="bg-card-bg/80 border border-white/10 rounded-2xl p-6">
            <h2 class="text-sm uppercase tracking-widest text-white/40 mb-4">Repository</h2>
            <div class="space-y-3">
                @if(!empty($stats['description']))
                    <p class="text-white/70 text-sm leading-relaxed">{{ $stats['description'] }}</p>
                @endif
                <div class="flex items-center gap-4 text-sm text-white/60">
                    <span class="flex items-center gap-1.5">⭐ {{ number_format($stats['stars'] ?? 0) }}</span>
                    <span class="flex items-center gap-1.5">🍴 {{ number_format($stats['forks'] ?? 0) }}</span>
                    @if(!empty($stats['language']))
                        <span class="flex items-center gap-1.5">💻 {{ $stats['language'] }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="bg-card-bg/80 border border-white/10 rounded-2xl p-6">
            <h2 class="text-sm uppercase tracking-widest text-white/40 mb-4">Timeline</h2>
            @if(!empty($stats['first_commit']))
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-2xl">🗓</span>
                    <div>
                        <div class="text-white font-semibold">First Commit</div>
                        <div class="text-white/50 text-sm">{{ \Carbon\Carbon::parse($stats['first_commit'])->format('M j, Y') }}</div>
                    </div>
                </div>
                <div class="text-white/40 text-sm">
                    That's {{ \Carbon\Carbon::parse($stats['first_commit'])->longAbsoluteDiffForHumans() }} of contributions!
                </div>
            @else
                <p class="text-white/30 text-sm">First commit date unavailable</p>
            @endif
        </div>
    </div>

    {{-- Language breakdown --}}
    @if(!empty($stats['languages']))
    <div class="bg-card-bg/80 border border-white/10 rounded-2xl p-6 mb-8">
        <h2 class="text-sm uppercase tracking-widest text-white/40 mb-4">Language Breakdown</h2>
        @php
            $langColors = ['#7c3aed','#06b6d4','#f43f5e','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6'];
        @endphp
        {{-- Bar --}}
        <div class="flex rounded-full overflow-hidden h-3 mb-5 gap-0.5">
            @foreach($stats['languages'] as $i => $lang)
                <div class="h-full transition-all duration-500"
                     style="width: {{ $lang['percentage'] }}%; background-color: {{ $langColors[$i % count($langColors)] }}"
                     title="{{ $lang['name'] }}: {{ $lang['percentage'] }}%"></div>
            @endforeach
        </div>
        {{-- Legend --}}
        <div class="flex flex-wrap gap-x-5 gap-y-2">
            @foreach(array_slice($stats['languages'], 0, 8) as $i => $lang)
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-3 h-3 rounded-sm flex-shrink-0"
                          style="background-color: {{ $langColors[$i % count($langColors)] }}"></span>
                    <span class="text-white/70">{{ $lang['name'] }}</span>
                    <span class="text-white/30">{{ $lang['percentage'] }}%</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Sparkline --}}
    @if(!empty($stats['weekly_commits']) && count(array_filter($stats['weekly_commits'])) > 0)
    <div class="bg-card-bg/80 border border-white/10 rounded-2xl p-6 mb-8">
        <h2 class="text-sm uppercase tracking-widest text-white/40 mb-4">Weekly Commit Activity</h2>
        @php
            $weeks  = $stats['weekly_commits'];
            $max    = max(array_filter($weeks) ?: [1]);
            $w      = 800;
            $h      = 80;
            $n      = count($weeks);
            $points = '';
            foreach ($weeks as $i => $c) {
                $x = round($i / max($n - 1, 1) * $w, 2);
                $y = round($h - ($c / $max) * ($h - 8), 2);
                $points .= "{$x},{$y} ";
            }
        @endphp
        <div class="w-full overflow-x-auto">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-20" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.6"/>
                        <stop offset="100%" stop-color="#7c3aed" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <polygon points="{{ $points }} {{ $w }},{{ $h }} 0,{{ $h }}"
                         fill="url(#sparkGrad)" opacity="0.4"/>
                <polyline points="{{ $points }}"
                          fill="none" stroke="#7c3aed" stroke-width="2.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>
    @endif

    {{-- Share card --}}
    <div class="bg-gradient-to-br from-violet-900/30 to-cyan-900/20 border border-violet-500/20 rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-2">Share your stats</h2>
        <p class="text-white/50 mb-6 text-sm">Drop this into your GitHub profile README to show off your contributions.</p>

        <div class="space-y-4">
            {{-- Markdown snippet --}}
            <div>
                <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Markdown embed</label>
                <div class="flex items-center gap-2">
                    <code id="markdown_snippet"
                          class="flex-1 bg-black/30 border border-white/10 rounded-xl px-4 py-3 text-sm text-cyan-300 break-all font-mono">
                        ![RepoWrapped]({{ route('card.show', compact('owner', 'repo', 'username')) }}?theme=dark)
                    </code>
                    <button id="copy_markdown_btn"
                            onclick="copyToClipboard('markdown_snippet', this)"
                            class="flex-shrink-0 bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold px-4 py-3 rounded-xl transition-all duration-200 hover:scale-105 active:scale-100">
                        Copy
                    </button>
                </div>
            </div>

            {{-- Card URL --}}
            <div>
                <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Card URL</label>
                <div class="flex items-center gap-2">
                    <code id="card_url"
                          class="flex-1 bg-black/30 border border-white/10 rounded-xl px-4 py-3 text-sm text-cyan-300 break-all font-mono">
                        {{ route('card.show', compact('owner', 'repo', 'username')) }}?theme=dark
                    </code>
                    <button id="copy_url_btn"
                            onclick="copyToClipboard('card_url', this)"
                            class="flex-shrink-0 bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold px-4 py-3 rounded-xl transition-all duration-200 hover:scale-105 active:scale-100">
                        Copy
                    </button>
                </div>
            </div>

            {{-- Theme switcher + live preview --}}
            <div class="pt-2">
                <label class="text-xs uppercase tracking-widest text-white/40 mb-3 block">Card preview</label>
                <div class="flex flex-wrap gap-2 mb-4" id="theme_switcher">
                    @foreach(['light', 'dark', 'radical', 'merko'] as $theme)
                        <button onclick="switchTheme('{{ $theme }}')"
                                data-theme="{{ $theme }}"
                                class="theme-btn px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200
                                       {{ $theme === 'dark' ? 'bg-violet-600 border-violet-500 text-white' : 'bg-card-bg/80 border-white/10 text-white/60 hover:border-violet-500/50 hover:text-white' }}">
                            {{ ucfirst($theme) }}
                        </button>
                    @endforeach
                </div>
                <div class="bg-card-bg/80 border border-white/10 rounded-xl p-4 overflow-x-auto">
                    <img id="card_preview"
                         src="{{ route('card.show', compact('owner', 'repo', 'username')) }}?theme=dark"
                         alt="Your RepoWrapped card"
                         class="max-w-full rounded-lg shadow-2xl">
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function copyToClipboard(elementId, btn) {
    const text = document.getElementById(elementId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.textContent;
        btn.textContent = '✓ Copied!';
        btn.classList.add('bg-emerald-600');
        btn.classList.remove('bg-violet-600');
        setTimeout(() => {
            btn.textContent = original;
            btn.classList.remove('bg-emerald-600');
            btn.classList.add('bg-violet-600');
        }, 2000);
    });
}

function switchTheme(theme) {
    const base = '{{ route('card.show', compact('owner', 'repo', 'username')) }}';
    const themeUrl = `${base}?theme=${theme}`;

    // Update preview image
    document.getElementById('card_preview').src = `${themeUrl}&t=${Date.now()}`;

    // Update share links
    document.getElementById('markdown_snippet').textContent = `![RepoWrapped](${themeUrl})`;
    document.getElementById('card_url').textContent = themeUrl;

    document.querySelectorAll('.theme-btn').forEach(btn => {
        if (btn.dataset.theme === theme) {
            btn.classList.add('bg-violet-600', 'border-violet-500', 'text-white');
            btn.classList.remove('bg-card-bg/80', 'border-white/10', 'text-white/60');
        } else {
            btn.classList.remove('bg-violet-600', 'border-violet-500', 'text-white');
            btn.classList.add('bg-card-bg/80', 'border-white/10', 'text-white/60');
        }
    });
}
</script>
@endpush

@endsection
