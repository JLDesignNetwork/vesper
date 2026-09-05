<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SUNDAY CITY // {{ __('Secret Access Platform') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#06080d] text-slate-100 min-h-screen font-sans antialiased relative overflow-x-hidden selection:bg-emerald-500/30 selection:text-emerald-200">

    <!-- Ambient background glows & grid -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-40 w-96 h-96 bg-cyan-600/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/3 w-[30rem] h-[30rem] bg-indigo-600/10 rounded-full blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.03] bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:24px_24px]"></div>
    </div>

    <div class="relative z-10 flex flex-col min-h-screen">
        <!-- Top Bar -->
        <header class="border-b border-slate-800/80 bg-slate-950/60 backdrop-blur-md px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-950/60 border border-emerald-500/40 text-emerald-400 font-mono font-bold text-sm shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                    SC
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping opacity-75"></span>
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                </div>
                <div>
                    <div class="font-mono text-xs text-emerald-400 font-semibold tracking-wider flex items-center gap-2">
                        <span>{{ __('SUNDAY CITY CIPHER') }}</span>
                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-[10px] text-emerald-300">{{ __('ENCRYPTED') }}</span>
                    </div>
                    <div class="text-[11px] text-slate-400">{{ __('Classified Communication & Media Terminal') }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3 text-xs font-mono text-slate-400">
                <div class="hidden sm:flex items-center gap-2 bg-slate-900/80 border border-slate-800 px-3 py-1.5 rounded-md">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('PROTOCOL: LEVEL-4 CLEARANCE') }}</span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-xl">

                <!-- Notifications -->
                @if (session('status'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-950/50 border border-emerald-500/40 text-emerald-200 text-sm flex items-start gap-3 backdrop-blur-sm shadow-[0_0_20px_rgba(16,185,129,0.15)]">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="font-medium font-mono text-xs uppercase tracking-wider text-emerald-400">{{ __('STATUS BROADCAST') }}</p>
                            <p class="mt-0.5 text-xs text-emerald-100/90">{{ session('status') }}</p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-200 text-sm flex items-start gap-3 backdrop-blur-sm shadow-[0_0_20px_rgba(244,63,94,0.15)]">
                        <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="space-y-1">
                            <p class="font-medium font-mono text-xs uppercase tracking-wider text-rose-400">{{ __('ACCESS REJECTED') }}</p>
                            @foreach ($errors->all() as $error)
                                <p class="text-xs text-rose-100/90">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Terminal Container -->
                <div class="glass-panel-glow rounded-2xl p-6 sm:p-8 relative overflow-hidden">
                    <!-- Subtle corner accents -->
                    <div class="absolute top-0 left-0 w-3 h-3 border-t-2 border-l-2 border-emerald-400"></div>
                    <div class="absolute top-0 right-0 w-3 h-3 border-t-2 border-r-2 border-emerald-400"></div>
                    <div class="absolute bottom-0 left-0 w-3 h-3 border-b-2 border-l-2 border-emerald-400"></div>
                    <div class="absolute bottom-0 right-0 w-3 h-3 border-b-2 border-r-2 border-emerald-400"></div>

                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-mono text-xs mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>{{ __('COVERT TRANSMISSION GATEWAY') }}</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            {{ __('Secret Access Platform') }}
                        </h1>
                        <p class="text-xs text-slate-400 mt-2 font-mono">
                            {{ __('End-to-end verified messaging • Image & Video transfers • Real-time geolocation tracking') }}
                        </p>
                    </div>

                    <!-- Mode Toggle Tabs -->
                    @php
                        $targetCode = $targetRoomCode ?? old('code', request('c', ''));
                        $hasTarget = !empty($targetCode);
                    @endphp

                    <div class="grid grid-cols-2 p-1 bg-slate-900/90 border border-slate-800 rounded-xl mb-6 text-xs font-mono">
                        <button type="button" id="tab-join-btn" onclick="switchTab('join')" class="py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 {{ $hasTarget ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>{{ __('ENTER PASSKEY') }}</span>
                        </button>
                        <button type="button" id="tab-create-btn" onclick="switchTab('create')" class="py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ __('ESTABLISH CHANNEL') }}</span>
                        </button>
                    </div>

                    <!-- Tab 1: Enter / Join Secret Room -->
                    <div id="tab-join" class="space-y-4">
                        <form action="{{ $hasTarget ? route('rooms.verify', ['room' => $targetCode]) : route('rooms.verify.entry') }}" method="POST" id="join-form">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-slate-300 mb-1.5">
                                        {{ __('Channel Code / Slug') }}
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            name="code"
                                            id="join-code"
                                            value="{{ $targetCode }}"
                                            placeholder="e.g. CIPHER-9412 or custom code"
                                            class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-sm font-mono text-white placeholder-slate-500 uppercase transition-colors"
                                            required
                                            autocomplete="off"
                                        >
                                        <div class="absolute right-3 top-3 text-slate-500">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                            </svg>
                                        </div>
                                    </div>
                                    @if(!empty($roomTitle))
                                        <p class="text-xs text-emerald-400/90 mt-1.5 font-mono">
                                            Channel: <span class="font-bold">{{ $roomTitle }}</span>
                                        </p>
                                    @endif
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-mono uppercase tracking-wider text-slate-300">
                                            {{ __('Secret Passcode / PIN') }}
                                        </label>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ __('Case-sensitive') }}</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            name="passcode"
                                            id="join-passcode"
                                            placeholder="{{ __('Enter access passcode') }}"
                                            class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-sm font-mono text-white placeholder-slate-500 transition-colors pr-11"
                                            required
                                            autocomplete="current-password"
                                        >
                                        <button
                                            type="button"
                                            onclick="togglePasswordVisibility('join-passcode', this)"
                                            class="absolute right-3 top-3 text-slate-400 hover:text-slate-200 p-0.5"
                                            title="Toggle password view"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-slate-300 mb-1.5">
                                        {{ __('Your Operative Alias') }} <span class="text-slate-500 lowercase">({{ __('optional') }})</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="alias"
                                        placeholder="e.g. Agent Phoenix"
                                        maxlength="30"
                                        class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 transition-colors"
                                    >
                                </div>

                                <button
                                    type="submit"
                                    class="w-full mt-2 py-3.5 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 active:bg-emerald-600 text-slate-950 font-mono font-bold text-sm tracking-wider uppercase transition-all shadow-[0_0_20px_rgba(16,185,129,0.3)] flex items-center justify-center gap-2 group cursor-pointer"
                                >
                                    <span>{{ __('AUTHENTICATE & DECRYPT') }}</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: Create / Establish New Channel -->
                    <div id="tab-create" class="space-y-4 hidden">
                        <form action="{{ route('rooms.store') }}" method="POST">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-mono uppercase tracking-wider text-slate-300">
                                            {{ __('Channel Codename / Title') }}
                                        </label>
                                        <span class="text-[11px] text-slate-500 font-mono">{{ __('optional') }}</span>
                                    </div>
                                    <input
                                        type="text"
                                        name="title"
                                        placeholder="e.g. Project Sunday City Safehouse"
                                        maxlength="80"
                                        class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 transition-colors"
                                    >
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-mono uppercase tracking-wider text-slate-300">
                                            {{ __('Channel Code / Slug') }}
                                        </label>
                                        <button
                                            type="button"
                                            onclick="generateRandomCode()"
                                            class="text-[11px] text-cyan-400 hover:text-cyan-300 font-mono flex items-center gap-1 cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            {{ __('Auto-Generate') }}
                                        </button>
                                    </div>
                                    <input
                                        type="text"
                                        name="code"
                                        id="create-code"
                                        placeholder="Leave blank for auto-generated code"
                                        class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 rounded-xl px-4 py-3 text-sm font-mono text-white placeholder-slate-500 uppercase transition-colors"
                                    >
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-mono uppercase tracking-wider text-slate-300">
                                            {{ __('Secret Passcode / PIN') }} <span class="text-rose-400">*</span>
                                        </label>
                                        <button
                                            type="button"
                                            onclick="generateRandomPasscode()"
                                            class="text-[11px] text-cyan-400 hover:text-cyan-300 font-mono flex items-center gap-1 cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                            {{ __('Generate Secure PIN') }}
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            name="passcode"
                                            id="create-passcode"
                                            placeholder="{{ __('Choose a strong secret passcode') }}"
                                            class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 rounded-xl px-4 py-3 text-sm font-mono text-white placeholder-slate-500 transition-colors"
                                            required
                                            minlength="4"
                                        >
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1 font-mono">
                                        {{ __('Keep this passcode secret. Only people with this passcode can decrypt this channel.') }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-slate-300 mb-1.5">
                                        {{ __('Creator Alias') }}
                                    </label>
                                    <input
                                        type="text"
                                        name="alias"
                                        value="{{ app()->getLocale() === 'ru' ? 'Командир' : 'Commander' }}"
                                        placeholder="Your alias in the channel"
                                        maxlength="30"
                                        class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 transition-colors"
                                    >
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-wider text-slate-300 mb-1.5">
                                            {{ __('Expiration Timer') }}
                                        </label>
                                        <select
                                            name="expires_in_hours"
                                            class="w-full bg-slate-900/90 border border-slate-700/80 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 rounded-xl px-3 py-2.5 text-xs font-mono text-slate-200 transition-colors"
                                        >
                                            <option value="0">{{ __('Permanent (No Auto-Destruct)') }}</option>
                                            <option value="1">{{ __('1 Hour (Self-Destructs)') }}</option>
                                            <option value="24" selected>{{ __('24 Hours (Recommended)') }}</option>
                                            <option value="168">{{ __('7 Days') }}</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                                        <input
                                            type="checkbox"
                                            name="burn_after_reading"
                                            id="burn_after_reading"
                                            value="1"
                                            class="w-4 h-4 rounded border-slate-700 text-rose-500 focus:ring-rose-500 bg-slate-950 cursor-pointer"
                                        >
                                        <label for="burn_after_reading" class="text-xs text-slate-300 cursor-pointer select-none">
                                            <span class="font-bold text-rose-400 block font-mono">{{ __('Burn-After-Reading') }}</span>
                                            <span class="text-[10px] text-slate-400 block leading-tight">{{ __('Wipe messages once seen by recipient') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full mt-3 py-3.5 px-4 rounded-xl bg-cyan-500 hover:bg-cyan-400 active:bg-cyan-600 text-slate-950 font-mono font-bold text-sm tracking-wider uppercase transition-all shadow-[0_0_20px_rgba(6,182,212,0.3)] flex items-center justify-center gap-2 group cursor-pointer"
                                >
                                    <span>{{ __('INITIALIZE & ENTER SECURE CHANNEL') }}</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Capabilities Footer -->
                    <div class="mt-8 pt-6 border-t border-slate-800/80 grid grid-cols-3 gap-2 text-center text-[10px] font-mono text-slate-400">
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-emerald-400 text-base">🛡️</span>
                            <span>{{ __('AES HASHED ACCESS') }}</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-cyan-400 text-base">🛰️</span>
                            <span>{{ __('IP & GEO RADAR') }}</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-rose-400 text-base">💥</span>
                            <span>{{ __('REMOTE NUKE PURGE') }}</span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-6 text-xs text-slate-500 font-mono">
                    {{ __('Sunday City Network Infrastructure • Zero Cloud Logs Mode') }}
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(mode) {
            const joinTab = document.getElementById('tab-join');
            const createTab = document.getElementById('tab-create');
            const joinBtn = document.getElementById('tab-join-btn');
            const createBtn = document.getElementById('tab-create-btn');

            if (mode === 'join') {
                joinTab.classList.remove('hidden');
                createTab.classList.add('hidden');
                joinBtn.className = 'py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                createBtn.className = 'py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200';
            } else {
                joinTab.classList.add('hidden');
                createTab.classList.remove('hidden');
                createBtn.className = 'py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 bg-cyan-500/20 text-cyan-300 border border-cyan-500/30';
                joinBtn.className = 'py-2.5 px-3 rounded-lg font-medium transition-all text-center flex items-center justify-center gap-2 text-slate-400 hover:text-slate-200';
            }
        }

        function togglePasswordVisibility(fieldId, btn) {
            const input = document.getElementById(fieldId);
            if (input.type === 'password') {
                input.type = 'text';
                btn.classList.add('text-emerald-400');
            } else {
                input.type = 'password';
                btn.classList.remove('text-emerald-400');
            }
        }

        function generateRandomCode() {
            const prefixes = ['SHADOW', 'CIPHER', 'GHOST', 'OMEGA', 'SPECTRE', 'VECTOR', 'VALKYRIE'];
            const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
            const randomNum = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('create-code').value = `${prefix}-${randomNum}`;
        }

        function generateRandomPasscode() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789!#@';
            let pass = '';
            for (let i = 0; i < 10; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('create-passcode').value = pass;
        }

        // Dynamically point join form action if room code changes
        const joinCodeInput = document.getElementById('join-code');
        const joinForm = document.getElementById('join-form');
        if (joinCodeInput && joinForm) {
            joinCodeInput.addEventListener('input', function() {
                const code = this.value.trim().toUpperCase();
                if (code) {
                    joinForm.action = `/rooms/${encodeURIComponent(code)}/verify`;
                }
            });
            if (joinCodeInput.value.trim()) {
                joinForm.action = `/rooms/${encodeURIComponent(joinCodeInput.value.trim().toUpperCase())}/verify`;
            }
        }
    </script>
</body>
</html>
