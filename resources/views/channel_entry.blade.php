<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LanguageService::getDirection(app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Private Channel Access') }} — {{ $roomTitle ?: $targetRoomCode }}</title>

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

    <!-- Top Navigation / Language Bar -->
    <header class="w-full max-w-6xl mx-auto p-6 flex items-center justify-between z-10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg shadow-emerald-950/40">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <div>
                <h1 class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                    {{ __('Vesper') }}
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-slate-400 tracking-wider">Private Channel</span>
                </h1>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            @if(session('status'))
                <div class="mb-4 p-3.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

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

            <!-- Passcode Entry Card -->
            <div class="rounded-2xl bg-slate-900/70 border border-slate-800/80 backdrop-blur-xl shadow-2xl p-7 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-5">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-slate-800 border border-slate-700/60 text-xs font-mono text-emerald-400 mb-3">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ $targetRoomCode }}</span>
                    </div>
                    <h2 class="text-xl font-semibold text-white tracking-tight">
                        {{ $roomTitle ?: __('Private Channel Access') }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ __('Enter Channel PIN to unlock access') }}
                    </p>
                </div>

                @if(!empty($requiresPasskey))
                    <!-- Passkey-only channel (pra) -->
                    <div class="p-4 rounded-xl bg-amber-950/40 border border-amber-500/30 text-amber-200 text-xs space-y-2">
                        <div class="font-semibold">{{ __('This channel opens only with a biometric passkey') }}</div>
                        <p class="text-amber-300/80">{{ __('Enroll Touch ID, Face ID or another passkey in your profile, then open the channel again. A PIN cannot be used here.') }}</p>
                        <a href="{{ route('profile.show') }}" class="inline-block mt-1 px-3 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 text-amber-200 font-mono">{{ __('Go to profile') }}</a>
                    </div>
                @elseif(!empty($authMember))
                    <!-- Logged-in Member Quick PIN Unlock -->
                    <form method="POST" action="{{ route('rooms.verify', ['room' => $targetRoomCode]) }}" class="space-y-4">
                        @csrf

                        <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold font-mono text-sm">
                                    {{ strtoupper(substr($authMember->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-white">{{ $authMember->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $authMember->email }}</div>
                                </div>
                            </div>
                            <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                {{ __('Member') }}
                            </span>
                        </div>

                        <div>
                            <label for="passcode" class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ __('Channel PIN') }}
                            </label>
                            <input
                                id="passcode"
                                name="passcode"
                                type="password"
                                required
                                autofocus
                                placeholder="{{ __('Enter channel PIN') }}"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm font-mono focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span>{{ __('Unlock & Enter Channel') }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- Security Footer -->
            <p class="text-center text-[11px] text-slate-600 font-mono mt-6">
                Vesper Network • Ghostwire Protocol • {{ __('Discreet, encrypted private messaging platform') }}
            </p>
        </div>
    </main>

    <footer class="w-full py-4 text-center text-xs text-slate-600 font-mono">
        &copy; {{ date('Y') }} Vesper. All rights reserved.
    </footer>

</body>
</html>
