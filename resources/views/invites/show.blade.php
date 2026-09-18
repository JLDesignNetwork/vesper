<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Secure Channel Clearance') }} — {{ __('Vesper') }}</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% 0%, #171d2c 0%, #090c15 65%, #05070c 100%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-emerald-500/20 selection:text-emerald-300">

    <!-- Top Bar -->
    <header class="w-full max-w-4xl mx-auto p-6 flex items-center justify-between z-10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg shadow-emerald-950/40">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <div>
                <h1 class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                    {{ __('Vesper') }}
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">Clearance Invitation</span>
                </h1>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            @if($errors->any())
                <div class="mb-4 p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs space-y-1">
                    @foreach($errors->all() as $error)
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Invitation Card -->
            <div class="rounded-2xl bg-slate-900/70 border border-slate-800/80 backdrop-blur-xl shadow-2xl p-7 relative overflow-hidden text-center space-y-6">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 mx-auto flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-950/50">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                </div>

                <div class="space-y-1.5">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-emerald-400">{{ __('Encrypted Channel Invitation') }}</div>
                    <h2 class="text-xl font-bold text-white tracking-tight">
                        {{ $room->title ?: $room->code }}
                    </h2>
                    <p class="text-xs text-slate-400 font-mono">
                        {{ __('Channel ID: :code', ['code' => $room->code]) }}
                    </p>
                    @if($creator)
                        <p class="text-xs text-slate-500">
                            {{ __('Issued by :name', ['name' => $creator->name]) }}
                        </p>
                    @endif
                </div>

                <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-300 font-mono space-y-1">
                    <div class="text-slate-500 uppercase tracking-wider text-[10px]">{{ __('Invitation Code') }}</div>
                    <div class="text-base font-bold text-emerald-400 tracking-wider select-all">{{ $invitation->code }}</div>
                    @if($invitation->expires_at)
                        <div class="text-[10px] text-slate-500 pt-1">
                            {{ __('Expires :time', ['time' => $invitation->expires_at->diffForHumans()]) }}
                        </div>
                    @endif
                </div>

                @auth
                    <form method="POST" action="{{ route('invites.accept', $invitation->token) }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/40 hover:shadow-emerald-900/40 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span>{{ __('Accept Invitation as :user', ['user' => auth()->user()->name]) }}</span>
                        </button>
                    </form>
                @else
                    <div class="space-y-3">
                        <a
                            href="{{ route('login') }}"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/40 hover:shadow-emerald-900/40 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>{{ __('Sign In to Accept Invitation') }}</span>
                        </a>
                        <p class="text-[11px] text-slate-500">
                            {{ __('You must sign in or create a member account to join this private channel.') }}
                        </p>
                    </div>
                @endauth
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full max-w-4xl mx-auto p-6 text-center text-xs text-slate-600 font-mono">
        <p>{{ __('Ghostwire Protocol • Discreet Zero-Knowledge Architecture') }}</p>
    </footer>

</body>
</html>
