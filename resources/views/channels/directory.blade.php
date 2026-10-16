<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Public Channels') }} — {{ __('Vesper') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% 0%, #171d2c 0%, #090c15 65%, #05070c 100%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500/20 selection:text-emerald-300">

    <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-6 pb-4 flex items-center justify-between gap-4">
        <a href="{{ $viewer ? route('profile.show') : route('login') }}" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <span class="text-sm font-semibold tracking-tight text-white">{{ __('Vesper') }}</span>
        </a>

        @if($viewer)
            <a href="{{ route('profile.show') }}" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-200 text-xs font-medium">{{ __('Your channels') }}</a>
        @else
            <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium">{{ __('Sign in') }}</a>
        @endif
    </header>

    <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 pb-12 space-y-6">
        <div class="space-y-1.5">
            <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Public Channels') }}</h1>
            <p class="text-sm text-slate-400">{{ __('Channels anyone can find. Private channels never appear here.') }}</p>
        </div>

        <form method="GET" action="{{ route('channels.directory') }}" class="flex gap-2" role="search">
            <label for="directory-search" class="sr-only">{{ __('Search public channels') }}</label>
            <input
                id="directory-search"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('Search by name, description or code') }}"
                class="flex-1 px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60"
            >
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-sm font-medium">{{ __('Search') }}</button>
        </form>

        @if($channels->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-800 bg-slate-900/40 p-10 text-center space-y-2">
                <h2 class="text-base font-semibold text-white">{{ $search !== '' ? __('No public channels match ":search"', ['search' => $search]) : __('No public channels yet') }}</h2>
                <p class="text-xs text-slate-400">
                    @if($viewer)
                        {{ __('Create one from your profile and choose a public type.') }}
                    @else
                        {{ __('Sign in to create the first one.') }}
                    @endif
                </p>
            </div>
        @else
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($channels as $channel)
                    <li class="rounded-2xl bg-slate-900/70 border border-slate-800 p-5 flex flex-col gap-4">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="text-base font-semibold text-white tracking-tight break-words">{{ $channel->title ?: $channel->code }}</h2>
                                <span class="shrink-0 text-[10px] uppercase font-mono px-2 py-0.5 rounded border {{ $channel->isSecured() ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' }}">
                                    {{ $channel->isSecured() ? __('PIN') : __('Open') }}
                                </span>
                            </div>
                            @if($channel->description)
                                <p class="text-xs text-slate-400 break-words">{{ $channel->description }}</p>
                            @endif
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-3 text-xs">
                            <span class="font-mono text-slate-400" style="font-variant-numeric: tabular-nums">
                                {{ trans_choice(':count member|:count members', $channel->members_count, ['count' => $channel->members_count]) }}
                            </span>
                            @if($viewer)
                                <a href="{{ route('rooms.show', $channel->code) }}" class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium">
                                    {{ $channel->isSecured() ? __('Enter with PIN') : __('Join channel') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-medium">{{ __('Sign in to join') }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div>{{ $channels->links() }}</div>
        @endif
    </main>
</body>
</html>
