<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Vesper') }} — {{ __('Two-Factor Security Clearance') }}</title>

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
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 tracking-wider">Ghostwire 2FA</span>
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
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-cyan-500 to-indigo-500"></div>

                <!-- Icon & Heading -->
                <div class="text-center mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-950/80 border border-emerald-500/30 mx-auto flex items-center justify-center text-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.2)] mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-white tracking-tight">
                        {{ __('Security Clearance Required') }}
                    </h2>
                    <p class="text-xs text-slate-400 font-mono mt-1">
                        {{ __('Confirm your identity using your authenticator app') }}
                    </p>
                </div>

                <!-- Errors -->
                @if($errors->any())
                    <div class="mb-5 p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <!-- Challenge Form -->
                <form method="POST" action="{{ route('2fa.verify') }}" class="space-y-4">
                    @csrf

                    <!-- Standard 6-digit TOTP input -->
                    <div id="totp-input-box">
                        <label for="code" class="block text-xs font-mono text-slate-300 uppercase tracking-wider mb-2 text-center">
                            {{ __('6-Digit Security Code') }}
                        </label>
                        <input
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autofocus
                            autocomplete="one-time-code"
                            placeholder="000000"
                            class="w-full text-center tracking-[0.5em] font-mono text-2xl font-bold py-3.5 px-4 rounded-xl bg-slate-950/80 border border-slate-800 text-emerald-400 placeholder-slate-700 focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                        >
                    </div>

                    <!-- Emergency Backup Code input (hidden by default) -->
                    <div id="recovery-input-box" class="hidden">
                        <label for="recovery_code" class="block text-xs font-mono text-slate-300 uppercase tracking-wider mb-2 text-center">
                            {{ __('Emergency Recovery Code') }}
                        </label>
                        <input
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            maxlength="10"
                            placeholder="XXXX-XXXX"
                            class="w-full text-center uppercase tracking-widest font-mono text-lg font-bold py-3.5 px-4 rounded-xl bg-slate-950/80 border border-slate-800 text-amber-400 placeholder-slate-700 focus:outline-none focus:border-amber-500/60 focus:ring-1 focus:ring-amber-500/60 transition-colors"
                        >
                        <p class="text-[11px] text-slate-500 font-mono text-center mt-1.5">
                            {{ __('This single-use code will immediately grant entry.') }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm transition-all shadow-lg shadow-emerald-950/60 active:scale-[0.99] cursor-pointer"
                    >
                        {{ __('Authenticate Clearance') }}
                    </button>

                    <!-- Toggle between TOTP and Recovery Code -->
                    <div class="pt-2 text-center">
                        <button
                            type="button"
                            onclick="toggleRecoveryMode()"
                            id="toggle-mode-btn"
                            class="text-xs font-mono text-slate-400 hover:text-emerald-400 transition-colors underline decoration-slate-700"
                        >
                            {{ __('Use an emergency backup recovery code') }}
                        </button>
                    </div>
                </form>

                <!-- Recovery Email Link -->
                <div class="mt-5 pt-4 border-t border-slate-800/80 text-center">
                    <a href="{{ route('recovery.request') }}" class="text-[11px] font-mono text-slate-500 hover:text-slate-300 transition-colors">
                        {{ __('Lost device and backup codes? Request emergency recovery') }} &rarr;
                    </a>
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

    <script>
        let recoveryMode = false;
        function toggleRecoveryMode() {
            recoveryMode = !recoveryMode;
            const totpBox = document.getElementById('totp-input-box');
            const recoveryBox = document.getElementById('recovery-input-box');
            const toggleBtn = document.getElementById('toggle-mode-btn');

            if (recoveryMode) {
                totpBox.classList.add('hidden');
                recoveryBox.classList.remove('hidden');
                document.getElementById('code').value = '';
                document.getElementById('recovery_code').focus();
                toggleBtn.textContent = '{{ __("Use authenticator app code instead") }}';
            } else {
                recoveryBox.classList.add('hidden');
                totpBox.classList.remove('hidden');
                document.getElementById('recovery_code').value = '';
                document.getElementById('code').focus();
                toggleBtn.textContent = '{{ __("Use an emergency backup recovery code") }}';
            }
        }
    </script>
</body>
</html>
