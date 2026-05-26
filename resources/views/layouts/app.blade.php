<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RepoWrapped') — Your GitHub story, beautifully wrapped</title>
    <meta name="description" content="@yield('meta_description', 'Generate beautiful, shareable GitHub contribution stats for any repository.')">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-site-bg text-white font-sans antialiased">

    {{-- Navigation --}}
    <nav class="border-b border-white/10 bg-site-bg/80 backdrop-blur-xl sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/repo-wrapped-logo.avif') }}" alt="RepoWrapped Logo" class="h-8 w-auto">
            </a>

            <div class="flex items-center gap-4">
                @auth
                    <div class="flex items-center gap-3">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->github_username }}"
                             class="w-8 h-8 rounded-full ring-2 ring-violet-500/50">
                        <span class="text-sm text-white/70 hidden sm:block">{{ auth()->user()->github_username }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm text-white/50 hover:text-white transition-colors duration-200 px-3 py-1.5 rounded-lg hover:bg-white/10">
                            Sign out
                        </button>
                    </form>
                @else
                    <a href="{{ route('auth.github') }}"
                       class="flex items-center gap-2 bg-white text-black text-sm font-semibold px-4 py-2 rounded-full hover:bg-white/90 transition-all duration-200 shadow-lg shadow-white/10">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
                        </svg>
                        Sign in
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="max-w-6xl mx-auto px-4 mt-4">
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Main content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/10 mt-20">
        <div class="max-w-6xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-white/40">
            <p>© {{ date('Y') }} RepoWrapped. Built with ❤️ for the GitHub community.</p>
            <div class="flex items-center gap-6">
                <a href="https://github.com" target="_blank" rel="noopener" class="hover:text-white/70 transition-colors">GitHub</a>
                <span>Not affiliated with GitHub, Inc.</span>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
