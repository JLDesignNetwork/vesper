<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LanguageService::getDirection(app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Vesper') }} — {{ $needsSetup ? __('Admin Setup') : __('Sign In') }}</title>

    <!-- PWA & Mobile Meta -->
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">

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
                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-slate-400 tracking-wider">Private</span>
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

            <!-- Auth Card -->
            <div class="rounded-2xl bg-slate-900/70 border border-slate-800/80 backdrop-blur-xl shadow-2xl p-7 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-6">
                    @if($needsSetup)
                        <h2 class="text-xl font-semibold text-white tracking-tight">
                            {{ __('Admin Setup') }}
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ __('Create your primary administrator account and select your system language.') }}
                        </p>
                    @else
                        <!-- Tabs: Sign In / Register -->
                        <div class="flex items-center rounded-xl bg-slate-950/80 p-1 border border-slate-800 mb-4">
                            <button
                                type="button"
                                id="tab-btn-login"
                                onclick="switchAuthTab('login')"
                                class="flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all {{ !($isRegister ?? false) ? 'bg-emerald-500/20 text-emerald-300 font-semibold shadow-sm' : 'text-slate-400 hover:text-white' }} cursor-pointer"
                            >
                                {{ __('Sign In') }}
                            </button>
                            <button
                                type="button"
                                id="tab-btn-register"
                                onclick="switchAuthTab('register')"
                                class="flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all {{ ($isRegister ?? false) ? 'bg-emerald-500/20 text-emerald-300 font-semibold shadow-sm' : 'text-slate-400 hover:text-white' }} cursor-pointer"
                            >
                                {{ __('Create Account') }}
                            </button>
                        </div>
                    @endif
                </div>

                @if($needsSetup)
                    <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="name" class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ __('Username') }}
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                required
                                autofocus
                                placeholder="Admin"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ __('Email Address') }}
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                placeholder="admin@vesper.local"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div>
                            <label for="setup-preferred-locale" class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ __('Preferred Language') }} <span class="text-emerald-400">*</span>
                            </label>
                            <select
                                id="setup-preferred-locale"
                                name="preferred_locale"
                                required
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                                @foreach(\App\Services\LanguageService::supported() as $code => $lang)
                                    <option value="{{ $code }}" {{ old('preferred_locale', app()->getLocale()) === $code ? 'selected' : '' }}>
                                        {{ $lang['flag'] }} {{ $lang['name'] }} ({{ strtoupper($code) }}) - {{ $lang['native'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ __('Password') }}
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                placeholder="••••••••••••"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                            >
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span>{{ __('Create Admin Account') }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </div>
                    </form>
                @else
                    <!-- Sign In Panel -->
                    <div id="auth-panel-login" class="{{ ($isRegister ?? false) ? 'hidden' : '' }}">
                        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="login" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Username or Email') }}
                                </label>
                                <input
                                    id="login"
                                    name="login"
                                    type="text"
                                    value="{{ old('login') }}"
                                    required
                                    autofocus
                                    placeholder="{{ __('Username or Email') }}"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Password') }}
                                </label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    placeholder="••••••••••••"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <label class="flex items-center gap-2 cursor-pointer text-slate-400 hover:text-slate-300 select-none">
                                    <input type="checkbox" name="remember" value="1" class="rounded bg-slate-950 border-slate-800 text-emerald-500 focus:ring-0">
                                    <span>{{ __('Remember Me') }}</span>
                                </label>
                                <a href="{{ route('recovery.request') }}" class="font-mono text-slate-500 hover:text-emerald-400 transition-colors">
                                    {{ __('Emergency Recovery') }}
                                </a>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                                >
                                    <span>{{ __('Sign In') }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Register Panel -->
                    <div id="auth-panel-register" class="{{ !($isRegister ?? false) ? 'hidden' : '' }}">
                        <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="reg-name" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Member Display Name') }} <span class="text-emerald-400">*</span>
                                </label>
                                <input
                                    id="reg-name"
                                    name="name"
                                    type="text"
                                    value="{{ old('name') }}"
                                    required
                                    placeholder="Alex Sterling"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>

                            <div>
                                <label for="reg-email" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Email Address') }} <span class="text-emerald-400">*</span>
                                </label>
                                <input
                                    id="reg-email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    placeholder="alex@example.com"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>

                            <div>
                                <label for="reg-preferred-locale" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Preferred Language') }} <span class="text-emerald-400">*</span>
                                </label>
                                <select
                                    id="reg-preferred-locale"
                                    name="preferred_locale"
                                    required
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                                    <option value="" disabled {{ old('preferred_locale') ? '' : 'selected' }}>{{ __('-- Select Preferred Language --') }}</option>
                                    @foreach(\App\Services\LanguageService::supported() as $code => $lang)
                                        <option value="{{ $code }}" {{ old('preferred_locale') === $code ? 'selected' : '' }}>
                                            {{ $lang['flag'] }} {{ $lang['name'] }} ({{ strtoupper($code) }}) - {{ $lang['native'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="reg-password" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    {{ __('Password') }} <span class="text-emerald-400">*</span>
                                </label>
                                <input
                                    id="reg-password"
                                    name="password"
                                    type="password"
                                    required
                                    placeholder="••••••••••••"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition-colors"
                                >
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-sm shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all cursor-pointer flex items-center justify-center gap-2"
                                >
                                    <span>{{ __('Create Member Account') }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if(! $needsSetup)
                    <!-- Biometric Hardware Authentication (Touch ID / Face ID / Windows Hello) -->
                    <div id="biometric-login-container" class="mt-4 hidden">
                        <div class="relative flex py-2 items-center">
                            <div class="flex-grow border-t border-slate-800"></div>
                            <span class="flex-shrink mx-3 text-[10px] uppercase font-mono text-slate-500 tracking-wider">{{ __('Hardware Biometrics') }}</span>
                            <div class="flex-grow border-t border-slate-800"></div>
                        </div>

                        <button
                            type="button"
                            onclick="loginWithBiometrics()"
                            id="biometric-login-btn"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-950/90 hover:bg-slate-900 border border-emerald-500/30 hover:border-emerald-500/60 text-emerald-400 text-xs font-mono flex items-center justify-center gap-2.5 transition-all shadow-[0_0_15px_rgba(16,185,129,0.1)] active:scale-[0.99] cursor-pointer"
                        >
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span id="biometric-btn-text">{{ __('Sign In with Touch ID / Biometrics') }}</span>
                        </button>
                    </div>

                    <!-- External Social / OAuth Identity Providers (Google, Apple) -->
                    <div class="mt-4">
                        <div class="relative flex py-2 items-center">
                            <div class="flex-grow border-t border-slate-800"></div>
                            <span class="flex-shrink mx-3 text-[10px] uppercase font-mono text-slate-500 tracking-wider">{{ __('Or Continue With') }}</span>
                            <div class="flex-grow border-t border-slate-800"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 mt-1">
                            <!-- Apple SSO -->
                            <a
                                href="{{ route('oauth.redirect', ['provider' => 'apple']) }}"
                                class="py-2.5 px-3 rounded-xl bg-slate-950/90 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-medium flex items-center justify-center gap-2 transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 170 170">
                                    <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.7-3.04-7.7-7.92-12-14.64-5.99-9.36-10.76-20.44-14.31-33.24-3.55-12.8-5.33-24.81-5.33-36.03 0-14.36 3.42-26.24 10.27-35.64 6.85-9.4 15.68-14.24 26.5-14.52 4.9.11 10.3 1.34 16.21 3.7 5.91 2.36 10.02 3.65 12.33 3.86 1.74-.21 6.09-1.55 13.06-4.01 6.96-2.47 12.8-3.65 17.5-3.55 10.23.66 18.7 4.54 25.42 11.66-8.92 5.44-13.26 13.04-13.02 22.8.24 7.62 3.21 14.07 8.91 19.35 5.7 5.28 12.44 8.27 20.22 8.97-2.17 6.32-4.8 12.41-7.88 18.27zm-38.42-120.91c0 7.39-2.61 14.19-7.83 20.4-5.22 6.21-11.45 10.01-18.7 11.4-1.31-6.97.22-13.91 4.59-20.81 4.37-6.9 10.6-11.23 18.7-12.99 2.17 7.02 3.24 13.69 3.24 20z" />
                                </svg>
                                <span>Apple</span>
                            </a>

                            <!-- Google SSO -->
                            <a
                                href="{{ route('oauth.redirect', ['provider' => 'google']) }}"
                                class="py-2.5 px-3 rounded-xl bg-slate-950/90 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-medium flex items-center justify-center gap-2 transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                </svg>
                                <span>Google</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Discrete Security Footer Note -->
            <p class="text-center text-[11px] text-slate-600 font-mono mt-6">
                Vesper Network • Ghostwire Protocol • {{ __('Discreet, encrypted private messaging platform') }}
            </p>
        </div>
    </main>

    <footer class="w-full py-4 text-center text-xs text-slate-600 font-mono">
        &copy; {{ date('Y') }} Vesper. All rights reserved.
    </footer>

    <script>
        function switchAuthTab(tab) {
            const loginPanel = document.getElementById('auth-panel-login');
            const registerPanel = document.getElementById('auth-panel-register');
            const btnLogin = document.getElementById('tab-btn-login');
            const btnRegister = document.getElementById('tab-btn-register');

            if (tab === 'register') {
                if (loginPanel) loginPanel.classList.add('hidden');
                if (registerPanel) registerPanel.classList.remove('hidden');
                if (btnLogin) {
                    btnLogin.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all text-slate-400 hover:text-white cursor-pointer';
                }
                if (btnRegister) {
                    btnRegister.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all bg-emerald-500/20 text-emerald-300 font-semibold shadow-sm cursor-pointer';
                }
            } else {
                if (registerPanel) registerPanel.classList.add('hidden');
                if (loginPanel) loginPanel.classList.remove('hidden');
                if (btnLogin) {
                    btnLogin.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all bg-emerald-500/20 text-emerald-300 font-semibold shadow-sm cursor-pointer';
                }
                if (btnRegister) {
                    btnRegister.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all text-slate-400 hover:text-white cursor-pointer';
                }
            }
        }

        // Check platform authenticator support (Touch ID / Face ID / Windows Hello)
        document.addEventListener('DOMContentLoaded', async () => {
            const bioContainer = document.getElementById('biometric-login-container');
            if (bioContainer && window.PublicKeyCredential) {
                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    if (available) {
                        bioContainer.classList.remove('hidden');
                    }
                } catch (e) {
                    console.debug('Biometrics not available:', e);
                }
            }
        });

        function bufferDecode(value) {
            return Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
        }

        function bufferEncode(value) {
            return btoa(String.fromCharCode.apply(null, new Uint8Array(value)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=/g, "");
        }

        async function loginWithBiometrics() {
            const btnText = document.getElementById('biometric-btn-text');
            const originalText = btnText.textContent;
            btnText.textContent = '{{ __("Awaiting Biometrics...") }}';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const optRes = await fetch('{{ route("webauthn.login.options") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const options = await optRes.json();

                options.challenge = bufferDecode(options.challenge);
                if (options.allowCredentials && options.allowCredentials.length > 0) {
                    options.allowCredentials = options.allowCredentials.map(c => {
                        c.id = bufferDecode(c.id);
                        return c;
                    });
                } else {
                    delete options.allowCredentials;
                }

                const assertion = await navigator.credentials.get({ publicKey: options });
                if (!assertion) {
                    throw new Error('No assertion returned');
                }

                const payload = {
                    id: assertion.id,
                    clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON))),
                    authenticatorData: btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData))),
                    signature: btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature))),
                };

                const verifyRes = await fetch('{{ route("webauthn.login.verify") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(payload)
                });

                const result = await verifyRes.json();
                if (result.success) {
                    btnText.textContent = '{{ __("Clearance Verified...") }}';
                    window.location.href = result.redirect;
                } else {
                    alert(result.message || '{{ __("Biometric verification failed.") }}');
                    btnText.textContent = originalText;
                }
            } catch (err) {
                console.error(err);
                btnText.textContent = originalText;
                if (err.name !== 'NotAllowedError') {
                    alert('{{ __("Biometric authentication error or canceled.") }}');
                }
            }
        }
    </script>
</body>
</html>
