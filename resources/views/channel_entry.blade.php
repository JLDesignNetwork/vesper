<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
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
                    {{ __('Sunday City') }}
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

                @if(!empty($authMember))
                    <!-- Logged-in Member Quick PIN Unlock -->
                    <form method="POST" action="{{ route('rooms.verify', ['room' => $targetRoomCode]) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="mode" value="member_pin">

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
                @else
                    <!-- Unauthenticated: Tab Selector -->
                    <div class="flex items-center p-1 rounded-xl bg-slate-950/80 border border-slate-800 mb-5 font-mono text-xs">
                        <button
                            type="button"
                            id="tab-register-btn"
                            onclick="switchAuthTab('register')"
                            class="flex-1 py-1.5 rounded-lg text-center font-medium transition-all bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm cursor-pointer"
                        >
                            {{ __('Create Account') }}
                        </button>
                        <button
                            type="button"
                            id="tab-login-btn"
                            onclick="switchAuthTab('login')"
                            class="flex-1 py-1.5 rounded-lg text-center font-medium transition-all text-slate-400 hover:text-slate-200 cursor-pointer"
                        >
                            {{ __('Sign In') }}
                        </button>
                    </div>

                    <!-- Mode 1: Create Account & Enter -->
                    <form id="form-register" method="POST" action="{{ route('rooms.verify', ['room' => $targetRoomCode]) }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="mode" value="register">

                        <div>
                            <label for="reg-passcode" class="block text-xs font-medium text-slate-300 mb-1">
                                {{ __('Channel PIN') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="reg-passcode"
                                name="passcode"
                                type="password"
                                required
                                placeholder="{{ __('Enter channel PIN') }}"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm font-mono focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label for="reg-name" class="block text-xs font-medium text-slate-300 mb-1">
                                    {{ __('Username') }} <span class="text-rose-400">*</span>
                                </label>
                                <input
                                    id="reg-name"
                                    name="name"
                                    type="text"
                                    required
                                    placeholder="Username"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>
                            <div>
                                <label for="reg-email" class="block text-xs font-medium text-slate-300 mb-1">
                                    {{ __('Email') }} <span class="text-rose-400">*</span>
                                </label>
                                <input
                                    id="reg-email"
                                    name="email"
                                    type="email"
                                    required
                                    placeholder="name@example.com"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="reg-password" class="block text-xs font-medium text-slate-300 mb-1">
                                {{ __('Password') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="reg-password"
                                name="password"
                                type="password"
                                required
                                placeholder="{{ __('Create a password (min. 6 characters)') }}"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <!-- Optional Profile Details -->
                        <div class="grid grid-cols-2 gap-2.5 pt-1">
                            <div>
                                <label for="reg-birthday" class="block text-xs font-medium text-slate-400 mb-1">
                                    {{ __('Birthday') }}
                                </label>
                                <input
                                    id="reg-birthday"
                                    name="birthday"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-200 text-xs font-mono focus:outline-none focus:border-emerald-500/60"
                                >
                            </div>
                            <div>
                                <label for="reg-gender" class="block text-xs font-medium text-slate-400 mb-1">
                                    {{ __('Gender') }}
                                </label>
                                <select
                                    id="reg-gender"
                                    name="gender"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500/60"
                                >
                                    <option value="">{{ __('Prefer not to say') }}</option>
                                    <option value="Male">{{ __('Male') }}</option>
                                    <option value="Female">{{ __('Female') }}</option>
                                    <option value="Non-binary">{{ __('Non-binary') }}</option>
                                    <option value="Other">{{ __('Other') }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="reg-location" class="block text-xs font-medium text-slate-400 mb-1">
                                {{ __('Location / City') }}
                            </label>
                            <input
                                id="reg-location"
                                name="location"
                                type="text"
                                placeholder="e.g. Rome, Italy"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-xs focus:outline-none focus:border-emerald-500/60"
                            >
                        </div>

                        <div>
                            <label class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-emerald-500/40 cursor-pointer transition-colors select-none">
                                <input
                                    type="checkbox"
                                    name="email_notifications"
                                    value="1"
                                    class="mt-0.5 rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <div class="text-xs">
                                    <span class="font-medium text-slate-300 block">{{ __('Email Notifications') }}</span>
                                    <span class="text-slate-500 text-[11px] block leading-snug">{{ __('Receive email notifications when new messages are posted in this channel.') }}</span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span>{{ __('Register & Enter Channel') }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </div>
                    </form>

                    <!-- Mode 2: Sign In & Enter -->
                    <form id="form-login" method="POST" action="{{ route('rooms.verify', ['room' => $targetRoomCode]) }}" class="space-y-3.5 hidden">
                        @csrf
                        <input type="hidden" name="mode" value="login">

                        <div>
                            <label for="log-passcode" class="block text-xs font-medium text-slate-300 mb-1">
                                {{ __('Channel PIN') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="log-passcode"
                                name="passcode"
                                type="password"
                                required
                                placeholder="{{ __('Enter channel PIN') }}"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm font-mono focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div>
                            <label for="log-login" class="block text-xs font-medium text-slate-300 mb-1">
                                {{ __('Username or Email') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="log-login"
                                name="login"
                                type="text"
                                required
                                placeholder="Username or email"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div>
                            <label for="log-password" class="block text-xs font-medium text-slate-300 mb-1">
                                {{ __('Password') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="log-password"
                                name="password"
                                type="password"
                                required
                                placeholder="Your password"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span>{{ __('Sign In & Enter Channel') }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <script>
                function switchAuthTab(tab) {
                    const regBtn = document.getElementById('tab-register-btn');
                    const logBtn = document.getElementById('tab-login-btn');
                    const regForm = document.getElementById('form-register');
                    const logForm = document.getElementById('form-login');

                    if (tab === 'register') {
                        regBtn.className = 'flex-1 py-1.5 rounded-lg text-center font-medium transition-all bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm cursor-pointer';
                        logBtn.className = 'flex-1 py-1.5 rounded-lg text-center font-medium transition-all text-slate-400 hover:text-slate-200 cursor-pointer';
                        regForm.classList.remove('hidden');
                        logForm.classList.add('hidden');
                    } else {
                        logBtn.className = 'flex-1 py-1.5 rounded-lg text-center font-medium transition-all bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm cursor-pointer';
                        regBtn.className = 'flex-1 py-1.5 rounded-lg text-center font-medium transition-all text-slate-400 hover:text-slate-200 cursor-pointer';
                        logForm.classList.remove('hidden');
                        regForm.classList.add('hidden');
                    }
                }
            </script>

            <!-- Security Footer -->
            <p class="text-center text-[11px] text-slate-600 font-mono mt-6">
                Sunday City Network • {{ __('Discreet, encrypted private messaging platform') }}
            </p>
        </div>
    </main>

    <footer class="w-full py-4 text-center text-xs text-slate-600 font-mono">
        &copy; {{ date('Y') }} Sunday City. All rights reserved.
    </footer>

</body>
</html>
