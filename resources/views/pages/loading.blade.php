@extends('layouts.app')

@section('title', 'Computing your story...')

@section('content')
<div class="relative min-h-[70vh] flex flex-col items-center justify-center overflow-hidden">

    {{-- Animated background orbs --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-violet-600/20 rounded-full blur-[120px] animate-pulse"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] h-[300px] bg-cyan-500/10 rounded-full blur-[80px] animate-pulse" style="animation-delay: 1s"></div>
    </div>

    <div class="relative z-10 text-center px-4">
        {{-- Progress ring --}}
        <div class="relative inline-flex items-center justify-center mb-8">
            <svg class="w-32 h-32 transform -rotate-90">
                <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="4" fill="transparent" class="text-white/5"/>
                <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="4" fill="transparent"
                        class="text-violet-500 transition-all duration-1000 ease-out"
                        stroke-dasharray="364.4"
                        stroke-dashoffset="364.4"
                        id="progress_ring"/>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <img src="{{ asset('images/favicon.ico') }}" alt="Logo" class="w-12 h-12 animate-bounce">
            </div>
        </div>

        <h1 class="text-3xl font-extrabold mb-4 bg-gradient-to-r from-violet-400 via-pink-400 to-cyan-400 bg-clip-text text-transparent">
            Computing your story...
        </h1>
        <p class="text-white/50 max-w-md mx-auto leading-relaxed">
            We're fetching your contributions from GitHub and wrapping them up for you.
            This usually takes a few seconds.
        </p>

        {{-- Status updates --}}
        <div class="mt-8 space-y-2 h-6 overflow-hidden">
            <div id="status_message" class="text-xs uppercase tracking-widest text-violet-400 font-semibold animate-pulse">
                Initiating GitHub API handshake...
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const owner = '{{ $owner }}';
    const repo = '{{ $repo }}';
    const username = '{{ $username }}';
    const statusUrl = `{{ route('stats.status', ['owner' => '__owner__', 'repo' => '__repo__', 'username' => '__username__']) }}`
        .replace('__owner__', owner)
        .replace('__repo__', repo)
        .replace('__username__', username);

    const ring = document.getElementById('progress_ring');
    const msg = document.getElementById('status_message');
    const totalLines = 364.4;
    let progress = 0;

    const messages = [
        'Connecting to GitHub...',
        'Filtering commit history...',
        'Counting lines of code...',
        'Analyzing language breakdown...',
        'Generating sparkline activity...',
        'Almost there, final polish...',
        'Wrapping it all up...'
    ];
    let msgIndex = 0;

    function updateProgress(p) {
        progress = p;
        const offset = totalLines - (p / 100 * totalLines);
        ring.style.strokeDashoffset = offset;
    }

    function checkStatus() {
        fetch(statusUrl)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'fresh' || data.status === 'stale') {
                    updateProgress(100);
                    msg.textContent = 'Redirecting...';
                    setTimeout(() => {
                        window.location.href = `{{ route('stats.show', ['owner' => $owner, 'repo' => $repo, 'username' => $username]) }}`;
                    }, 500);
                    return;
                }

                // Simulate progress
                if (progress < 90) {
                    updateProgress(progress + Math.random() * 15);
                }

                // Cycle messages
                msgIndex = (msgIndex + 1) % messages.length;
                msg.textContent = messages[msgIndex];

                setTimeout(checkStatus, 2000);
            })
            .catch(() => {
                setTimeout(checkStatus, 3000);
            });
    }

    // Start polling
    setTimeout(checkStatus, 1000);
</script>
@endpush
@endsection
