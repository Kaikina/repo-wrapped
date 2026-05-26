@extends('layouts.app')

@section('title', 'RepoWrapped')

@section('content')
<div class="relative overflow-hidden">

    {{-- Animated background orbs --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-violet-600/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute top-40 -right-20 w-80 h-80 bg-cyan-500/15 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
        <div class="absolute bottom-20 left-1/3 w-64 h-64 bg-pink-600/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s"></div>
    </div>

    {{-- Hero --}}
    <section class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 text-center">

        <div class="inline-flex items-center gap-2 bg-violet-500/10 border border-violet-500/20 rounded-full px-4 py-1.5 text-sm text-violet-300 mb-8">
            <span class="w-2 h-2 rounded-full bg-violet-400 animate-pulse"></span>
            Open source · No data stored beyond stats
        </div>

        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight mb-6 leading-tight">
            Your GitHub contribution<br>
            <span class="bg-gradient-to-r from-violet-400 via-pink-400 to-cyan-400 bg-clip-text text-transparent">
                story, beautifully wrapped
            </span>
        </h1>

        <p class="text-xl text-white/60 max-w-2xl mx-auto mb-12 leading-relaxed">
            Generate shareable stats and embeddable SVG cards for any public GitHub repository.
            Show off your commits, lines written, and contribution history.
        </p>

        {{-- Generate form --}}
        @auth
            <form method="POST" action="{{ route('stats.generate') }}"
                  class="max-w-xl mx-auto">
                @csrf
                <div class="flex flex-col sm:flex-row gap-3 bg-card-bg/80 backdrop-blur-xl border border-white/10 rounded-2xl p-4 shadow-2xl shadow-black/50">
                    <div class="flex-1">
                        <label for="owner_input" class="sr-only">Repository owner</label>
                        <input id="owner_input" type="text" name="owner"
                               placeholder="owner"
                               value="{{ old('owner') }}"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-all"
                               required>
                        @error('owner')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="text-white/30 flex items-center font-light text-xl hidden sm:flex">/</div>
                    <div class="flex-1">
                        <label for="repo_input" class="sr-only">Repository name</label>
                        <input id="repo_input" type="text" name="repo"
                               placeholder="repository"
                               value="{{ old('repo') }}"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-all"
                               required>
                        @error('repo')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button id="generate_btn" type="submit"
                            class="bg-gradient-to-r from-violet-600 to-cyan-600 hover:from-violet-500 hover:to-cyan-500 text-white font-semibold px-6 py-3 rounded-xl transition-all duration-200 shadow-lg shadow-violet-500/25 hover:shadow-violet-500/40 hover:scale-105 active:scale-100 whitespace-nowrap">
                        Generate ✨
                    </button>
                </div>
                <p class="text-white/30 text-sm mt-3">Stats will be generated for your GitHub account: <strong class="text-white/60">{{ auth()->user()->github_username }}</strong></p>
            </form>
        @else
            <div class="max-w-xl mx-auto">
                <a href="{{ route('auth.github') }}" id="signin_hero_btn"
                   class="inline-flex items-center gap-3 bg-gradient-to-r from-violet-600 to-cyan-600 hover:from-violet-500 hover:to-cyan-500 text-white font-semibold text-lg px-8 py-4 rounded-2xl transition-all duration-200 shadow-2xl shadow-violet-500/30 hover:shadow-violet-500/50 hover:scale-105 active:scale-100">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
                    </svg>
                    Connect to get started
                </a>
                <p class="text-white/30 text-sm mt-4">Only <code class="text-violet-300">read:user</code> scope is requested. We never write to your repos.</p>
            </div>
        @endauth
    </section>

    {{-- Features --}}
    <section class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach([
                ['🔥', 'Commit History', 'See your total commits, lines added/deleted, and your very first contribution date.'],
                ['📊', 'Language Breakdown', 'Visualise which languages make up the repo you contributed to the most.'],
                ['🃏', 'Embeddable SVG Card', 'Drop a beautiful, auto-updating stats card into any GitHub README with a single Markdown line.'],
                ['⚡', 'Lightning Cached', 'Two-layer Redis + Postgres cache means your card loads instantly every time.'],
                ['🎨', 'Multiple Themes', 'Choose from light, dark, radical (purple), and merko (green) card themes.'],
                ['🔒', 'Privacy First', 'Only public repository data. No email, no writes, no tracking.'],
            ] as [$icon, $title, $desc])
            <div class="group bg-card-bg/50 hover:bg-card-bg/80 border border-white/10 hover:border-violet-500/30 rounded-2xl p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-violet-500/10">
                <div class="text-3xl mb-4">{{ $icon }}</div>
                <h3 class="font-semibold text-white mb-2">{{ $title }}</h3>
                <p class="text-white/50 text-sm leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Example card preview --}}
    <section class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h2 class="text-3xl font-bold mb-4">What your card looks like</h2>
        <p class="text-white/50 mb-10">Drop this into your GitHub README and impress everyone.</p>
        <div class="inline-block bg-card-bg/80 border border-white/10 rounded-2xl p-1 shadow-2xl">
            <img src="{{ route('card.show', ['owner' => 'prestashop', 'repo' => 'prestashop', 'username' => 'kaikina']) }}?theme=dark"
                 alt="Example RepoWrapped card"
                 class="rounded-xl max-w-full"
                 onerror="this.style.display='none'">
        </div>
    </section>

</div>
@endsection
