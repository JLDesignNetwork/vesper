<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Vesper') }} — {{ __('Emergency Account Recovery') }}</title>

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
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 tracking-wider">Emergency Recovery</span>
                </h1>
            </div>
        </div>
        <div>
            <a href="{{ route('login') }}" class="text-xs font-mono text-slate-400 hover:text-white transition-colors">
                &larr; {{ __('Return to Login') }}
            </a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Card Container -->
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-xl p-7 shadow-2xl shadow-black/60 relative overflow-hidden">
                <!-- Ambient Accent line -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 via-rose-500 to-indigo-500"></div>

                <!-- Icon & Heading -->
                <div class="text-center mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-amber-950/60 border border-amber-500/30 mx-auto flex items-center justify-center text-amber-400 shadow-[0_0_20px_rgba(245,158,11,0.2)] mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-white tracking-tight">
                        {{ __('Account Recovery Dispatch') }}
                    </h2>
                    <p class="text-xs text-slate-400 font-mono mt-1">
                        {{ __('Transmit emergency access credentials to your verified secondary recovery channel') }}
                    </p>
                </div>

                <!-- Status Feedback -->
                @if(session('status'))
                    <div class="mb-5 p-3.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Errors -->
                @if($errors->any())
                    <div class="mb-5 p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('recovery.send') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="identifier" class="block text-xs font-mono text-slate-300 uppercase tracking-wider mb-2">
                            {{ __('Username or Primary Email') }}
                        </label>
                        <input
                            id="identifier"
                            name="identifier"
                            type="text"
                            required
                            autofocus
                            placeholder="e.g. operative or operative@vesper.local"
                            class="w-full py-2.5 px-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-amber-500/60 focus:ring-1 focus:ring-amber-500/60 transition-colors"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-medium text-sm transition-all shadow-lg shadow-amber-950/60 active:scale-[0.99] cursor-pointer"
                    >
                        {{ __('Dispatch Recovery Credentials') }}
                    </button>
                </form>

                <div class="mt-5 pt-4 border-t border-slate-800/80 text-center">
                    <p class="text-[11px] text-slate-500 font-mono">
                        {{ __('Note: For security, recovery links are only sent if a verified recovery email was linked in advance.') }}
                    </p>
                </div>
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
