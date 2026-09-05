<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Vesper') }} — {{ __('Reset Security Credentials') }}</title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700|jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

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

    <!-- Top Navigation -->
    <header class="w-full max-w-6xl mx-auto p-6 flex items-center justify-between z-10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg shadow-emerald-950/40 font-mono font-bold text-emerald-400 text-sm">
                V
            </div>
            <div>
                <h1 class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                    {{ __('Vesper') }}
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 tracking-wider">Credentials Reset</span>
                </h1>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Card Container -->
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-xl p-7 shadow-2xl shadow-black/60 relative overflow-hidden">
                <!-- Ambient Accent line -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>

                <!-- Icon & Heading -->
                <div class="text-center mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-950/80 border border-emerald-500/30 mx-auto flex items-center justify-center text-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.2)] mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-white tracking-tight">
                        {{ __('Create New Access Password') }}
                    </h2>
                    <p class="text-xs text-slate-400 font-mono mt-1">
                        {{ __('Operative Identity') }}: <strong class="text-emerald-400">{{ $user->name }}</strong>
                    </p>
                </div>

                <!-- Errors -->
                @if($errors->any())
                    <div class="mb-5 p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('recovery.reset') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="password" class="block text-xs font-mono text-slate-300 uppercase tracking-wider mb-2">
                            {{ __('New Password (min. 6 characters)') }}
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full py-2.5 px-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-mono text-slate-300 uppercase tracking-wider mb-2">
                            {{ __('Confirm New Password') }}
                        </label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            class="w-full py-2.5 px-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm transition-all shadow-lg shadow-emerald-950/60 active:scale-[0.99] cursor-pointer"
                    >
                        {{ __('Save Password & Enter Command Center') }}
                    </button>
                </form>
            </div>

            <!-- Footer Note -->
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
