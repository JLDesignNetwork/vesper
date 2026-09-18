<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LanguageService::getDirection(app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Member Profile & Channels') }} — {{ __('Vesper') }}</title>

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
        .custom-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 9999px;
        }
        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.4);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500/20 selection:text-emerald-300">

    <!-- Top Navigation Header -->
    <header class="w-full border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-xl sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg shadow-emerald-950/40">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                        {{ __('Vesper') }}
                        <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">{{ __('Private Network') }}</span>
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono flex items-center gap-1.5">
                        <span>{{ __('Member Command Hub') }}</span>
                        <span>•</span>
                        <span class="text-slate-300">{{ $user->name }}</span>
                    </div>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-2.5 font-mono text-xs">
                @if($user->isAdmin())
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-amber-300 text-xs flex items-center gap-1.5 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <span class="hidden sm:inline font-sans">{{ __('Admin Console') }}</span>
                    </a>
                @endif

                <!-- Pinless Access Status Pill -->
                <div
                    class="px-2.5 py-1.5 rounded-xl border text-xs flex items-center gap-1.5 {{ $canUsePinlessEntry ? 'bg-emerald-950/30 border-emerald-500/30 text-emerald-300' : 'bg-amber-950/30 border-amber-500/30 text-amber-300' }}"
                    title="{{ $canUsePinlessEntry ? __('Pinless Access Active') : __('Activate 2FA for Pinless Entry') }}"
                >
                    @if($canUsePinlessEntry)
                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <span class="hidden md:inline font-sans text-xs">{{ __('Pinless: Active') }}</span>
                    @else
                        <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span class="hidden md:inline font-sans text-xs">{{ __('PIN Required (No 2FA)') }}</span>
                    @endif
                </div>

                <!-- Sign Out -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-rose-950/40 border border-slate-800 hover:border-rose-500/30 text-slate-400 hover:text-rose-300 text-xs transition-colors cursor-pointer"
                        title="{{ __('Sign Out') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Container: Unified Split View -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

        <!-- Status Alerts -->
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-3 shadow-lg shadow-emerald-950/30 backdrop-blur-sm">
                <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="flex-1 font-medium">{{ session('status') }}</div>
            </div>
        @endif

        @if(session('error') || (isset($errors) && $errors->any()))
            <div class="p-4 rounded-2xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs space-y-1 shadow-lg shadow-rose-950/30 backdrop-blur-sm">
                @if(session('error'))
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @if(isset($errors))
                    @foreach($errors->all() as $error)
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        <!-- Two-Column Grid: Left Profile (5 cols) | Right Channels (7 cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- ========================================== -->
            <!-- LEFT COLUMN: MEMBER PROFILE & CREDENTIALS  -->
            <!-- ========================================== -->
            <div class="lg:col-span-5 space-y-6">

                <!-- Member Identity Card -->
                <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 shadow-xl backdrop-blur-md space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-400 uppercase tracking-wider font-mono">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            <span>{{ __('Member Profile') }}</span>
                        </div>
                        <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded border {{ $user->isAdmin() ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </div>

                    <!-- Profile Form (AJAX Saved) -->
                    <form id="profile-form" onsubmit="saveProfile(event)" class="space-y-4 text-xs">
                        @csrf

                        <div id="profile-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>

                        <!-- Custom Avatar Uploader -->
                        <div class="flex items-center gap-3.5 p-3.5 bg-slate-950/70 rounded-xl border border-slate-800">
                            <div class="relative group shrink-0">
                                <div id="profile-avatar-preview-wrap" class="w-16 h-16 rounded-2xl overflow-hidden bg-emerald-500/20 border-2 border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold font-mono text-xl shadow-inner">
                                    @if($user->avatar_path)
                                        <img id="profile-avatar-preview-img" src="{{ $user->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                        <span id="profile-avatar-preview-initial" class="hidden">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    @else
                                        <img id="profile-avatar-preview-img" src="" class="w-full h-full object-cover hidden" alt="">
                                        <span id="profile-avatar-preview-initial">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="text-[12px] font-semibold text-white">{{ __('Profile Photo') }}</div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onclick="document.getElementById('avatar-file-input').click()"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-medium cursor-pointer transition-colors"
                                    >
                                        {{ __('Change Photo') }}
                                    </button>
                                    <button
                                        type="button"
                                        id="remove-avatar-btn"
                                        onclick="markAvatarForRemoval()"
                                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium cursor-pointer transition-colors {{ $user->avatar_path ? '' : 'hidden' }}"
                                    >
                                        {{ __('Remove') }}
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-500 font-mono">{{ __('PNG, JPG, WEBP (Max 5MB)') }}</p>
                            </div>
                            <input type="file" id="avatar-file-input" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                            <input type="hidden" id="remove-avatar-flag" name="remove_avatar" value="0">
                        </div>

                        <!-- Name & Email -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-300 mb-1 font-mono">{{ __('Display Name') }} <span class="text-rose-400">*</span></label>
                                <input
                                    type="text"
                                    name="name"
                                    required
                                    value="{{ $user->name }}"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >
                            </div>
                            <div>
                                <label class="block font-medium text-slate-300 mb-1 font-mono">{{ __('Email Address') }} <span class="text-rose-400">*</span></label>
                                <input
                                    type="email"
                                    name="email"
                                    required
                                    value="{{ $user->email }}"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >
                            </div>
                        </div>

                        <!-- Location & Bio -->
                        <div class="space-y-3">
                            <div>
                                <label class="block font-medium text-slate-300 mb-1 font-mono">{{ __('Location') }}</label>
                                <input
                                    type="text"
                                    name="location"
                                    value="{{ $user->location }}"
                                    placeholder="e.g. Geneva, CH"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >
                            </div>
                            <div>
                                <label class="block font-medium text-slate-300 mb-1 font-mono">{{ __('Bio / Designation') }}</label>
                                <textarea
                                    name="bio"
                                    rows="2"
                                    placeholder="Add brief member notes..."
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >{{ $user->bio }}</textarea>
                            </div>
                        </div>

                        <!-- Language Preference -->
                        <div class="p-3 bg-slate-950/70 rounded-xl border border-slate-800 space-y-1.5">
                            @php
                                $supportedLangs = \App\Services\LanguageService::supported();
                                $activeLocale = $user->preferred_locale ?? $user->effectiveLocale();
                            @endphp
                            <div class="flex items-center justify-between">
                                <label class="block font-medium text-slate-300 text-xs flex items-center gap-1.5 font-mono">
                                    <span>🌐</span>
                                    <span>{{ __('Preferred Language') }}</span>
                                </label>
                                <span class="text-[10px] font-mono text-emerald-400">
                                    {{ $supportedLangs[$activeLocale]['name'] ?? strtoupper($activeLocale) }}
                                </span>
                            </div>
                            <select
                                name="preferred_locale"
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500 font-sans cursor-pointer"
                            >
                                @foreach($supportedLangs as $code => $lang)
                                    <option value="{{ $code }}" {{ $activeLocale === $code ? 'selected' : '' }}>
                                        {{ $lang['flag'] }} {{ $lang['name'] }} ({{ strtoupper($code) }}) - {{ $lang['native'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Privacy Controls -->
                        <div class="p-3 bg-slate-950/70 rounded-xl border border-slate-800 space-y-2">
                            <div class="text-[11px] font-semibold text-slate-400 font-mono uppercase tracking-wider">{{ __('Privacy Controls') }}</div>
                            <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 font-sans">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="hide_location" value="1" {{ $user->hide_location ? 'checked' : '' }} class="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-0">
                                    <span>{{ __('Hide Location') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="hide_bio" value="1" {{ $user->hide_bio ? 'checked' : '' }} class="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-0">
                                    <span>{{ __('Hide Bio') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Change Password Fields -->
                        <div class="p-3 bg-slate-950/70 rounded-xl border border-slate-800 space-y-2">
                            <div class="text-[11px] font-semibold text-slate-400 font-mono uppercase tracking-wider">{{ __('Change Password') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input
                                    type="password"
                                    name="current_password"
                                    placeholder="{{ __('Current Password') }}"
                                    class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >
                                <input
                                    type="password"
                                    name="password"
                                    placeholder="{{ __('New Password') }}"
                                    class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 font-sans"
                                >
                            </div>
                        </div>

                        <!-- Save Profile Button -->
                        <div class="pt-2 flex justify-end">
                            <button
                                type="submit"
                                id="profile-submit-btn"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-medium cursor-pointer transition-all shadow-lg shadow-emerald-950/30 flex items-center gap-2"
                            >
                                <svg class="w-3.5 h-3.5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>{{ __('Save Profile Settings') }}</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security & Two-Factor Authentication Card -->
                <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 shadow-xl backdrop-blur-md space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-400 uppercase tracking-wider font-mono">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            <span>{{ __('Security & 2FA Credentials') }}</span>
                        </div>
                        @if($canUsePinlessEntry)
                            <span class="text-[10px] text-emerald-300 font-mono bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-500/30">pinless active</span>
                        @endif
                    </div>

                    <!-- Authenticator App 2FA (TOTP) -->
                    <div class="p-3.5 bg-slate-950/70 rounded-xl border border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs font-medium text-white">{{ __('Authenticator App (TOTP)') }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Google Authenticator, 1Password, Authy') }}</div>
                            </div>
                            @if($user->hasTwoFactor())
                                <button
                                    type="button"
                                    onclick="disableTwoFactor()"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium cursor-pointer transition-colors"
                                >
                                    {{ __('Disable') }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    onclick="initiateTwoFactorSetup()"
                                    class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium cursor-pointer transition-colors shadow-sm"
                                >
                                    {{ __('Setup') }}
                                </button>
                            @endif
                        </div>

                        <!-- Inline 2FA Setup Container -->
                        <div id="totp-setup-container" class="hidden pt-3 border-t border-slate-800 space-y-3">
                            <div class="text-center p-3 bg-slate-900 rounded-xl border border-slate-800">
                                <div id="totp-qr-wrap" class="flex justify-center my-2"></div>
                                <div class="text-[10px] text-slate-400 font-mono select-all mt-1" id="totp-secret-key"></div>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    id="totp-confirm-code"
                                    maxlength="6"
                                    placeholder="123456"
                                    class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-center text-sm font-mono tracking-widest text-slate-100 focus:outline-none focus:border-emerald-500"
                                >
                                <button
                                    type="button"
                                    onclick="confirmTwoFactorSetup()"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-medium cursor-pointer shrink-0"
                                >
                                    {{ __('Confirm') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hardware Biometrics / Passkeys -->
                    <div class="p-3.5 bg-slate-950/70 rounded-xl border border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs font-medium text-white flex items-center gap-1.5">
                                    <span>🔑</span>
                                    <span>{{ __('Hardware Passkeys / Biometrics') }}</span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Touch ID, Face ID, Windows Hello') }}</div>
                            </div>
                            <button
                                type="button"
                                onclick="registerPasskey()"
                                class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium cursor-pointer transition-colors shadow-sm"
                            >
                                {{ __('Add Key') }}
                            </button>
                        </div>

                        @if($user->webauthnCredentials && $user->webauthnCredentials->isNotEmpty())
                            <div class="pt-2 border-t border-slate-800/80 space-y-1.5">
                                @foreach($user->webauthnCredentials as $passkey)
                                    <div class="flex items-center justify-between text-[11px] font-mono text-slate-300 bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                                        <span>🔑 {{ $passkey->name ?: __('Passkey').' #'.$loop->iteration }}</span>
                                        <button type="button" onclick="deletePasskey({{ $passkey->id }})" class="text-rose-400 hover:text-rose-300 text-[10px] cursor-pointer">{{ __('Remove') }}</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Emergency Recovery Email -->
                    <div class="p-3.5 bg-slate-950/70 rounded-xl border border-slate-800 space-y-2">
                        <label class="block font-medium text-xs text-white">{{ __('Emergency Recovery Email') }}</label>
                        <div class="text-[11px] text-slate-400">{{ __('Used to recover member credentials if 2FA device is lost.') }}</div>
                        <div class="flex gap-2">
                            <input
                                type="email"
                                id="recovery-email-input"
                                value="{{ $user->recovery_email }}"
                                placeholder="backup@example.com"
                                class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-emerald-500 font-sans"
                            >
                            <button
                                type="button"
                                onclick="saveRecoveryEmail()"
                                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium cursor-pointer transition-colors shrink-0"
                            >
                                {{ __('Save') }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- RIGHT COLUMN: TACTICAL CHANNELS INVENTORY  -->
            <!-- ========================================== -->
            <div class="lg:col-span-7 space-y-6">

                <!-- 2FA Encouragement Banner if Pinless is not yet active -->
                @if(! $canUsePinlessEntry)
                    <div class="rounded-2xl bg-gradient-to-r from-amber-950/40 via-slate-900/60 to-amber-950/20 border border-amber-500/30 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 backdrop-blur-md">
                        <div class="flex items-center gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white tracking-tight">{{ __('Unlock 1-Click Pinless Channel Entry') }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ __('Configure 2FA or a Passkey on the left panel to enter your channels instantly without typing PINs.') }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Pending Direct Channel Invitations Banner -->
                @if($pendingInvites->isNotEmpty())
                    <div class="rounded-2xl bg-slate-900/80 border border-cyan-500/30 p-5 space-y-3 backdrop-blur-md shadow-xl shadow-cyan-950/20">
                        <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider font-mono">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <span>{{ __('Pending Channel Invitations') }} ({{ $pendingInvites->count() }})</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($pendingInvites as $invite)
                                <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-white tracking-tight">{{ $invite->title ?: $invite->code }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                            {{ __('Invited by: :name', ['name' => $invite->creator?->name ?? 'Administrator']) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('channels.invites.accept', $invite->id) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium cursor-pointer transition-colors shadow-sm"
                                            >
                                                {{ __('Accept') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('channels.invites.decline', $invite->id) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium cursor-pointer transition-colors"
                                            >
                                                {{ __('Decline') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Channels Inventory Header & Redeem Trigger -->
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white tracking-tight flex items-center gap-2.5">
                            <span>{{ __('Your Channels') }}</span>
                            <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                                {{ $channels->count() }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">{{ __('Strict zero discovery: only enrolled channels are accessible.') }}</p>
                    </div>

                    <!-- Redeem Code Button -->
                    <button
                        type="button"
                        onclick="openRedeemModal()"
                        class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-medium flex items-center gap-2 cursor-pointer transition-colors shadow-sm"
                    >
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                        <span>{{ __('Redeem Invite Code') }}</span>
                    </button>
                </div>

                <!-- Channels List or Zero-Channel Empty State -->
                @if($channels->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($channels as $channel)
                            <div class="group rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition-all p-5 flex flex-col justify-between shadow-lg relative overflow-hidden backdrop-blur-md hover:shadow-emerald-950/20">
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 class="text-base font-semibold text-white tracking-tight group-hover:text-emerald-300 transition-colors">
                                                {{ $channel->title ?: $channel->code }}
                                            </h3>
                                            <div class="text-xs text-slate-400 font-mono mt-0.5 flex items-center gap-1.5">
                                                <span class="text-slate-500">ID:</span>
                                                <span>{{ $channel->code }}</span>
                                            </div>
                                        </div>
                                        <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded border {{ $channel->pivot->role === 'owner' ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-slate-800 border-slate-700 text-slate-400' }}">
                                            {{ $channel->pivot->role ?? 'member' }}
                                        </span>
                                    </div>

                                    <!-- Metadata metrics -->
                                    <div class="pt-2 border-t border-slate-800/60 flex items-center justify-between text-xs font-mono text-slate-400">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                            <span>{{ $channel->messages_count }} {{ __('msgs') }}</span>
                                        </div>
                                        <div>
                                            @if($channel->pivot->last_accessed_at)
                                                <span class="text-slate-500">{{ __('Last entry:') }}</span> {{ \Carbon\Carbon::parse($channel->pivot->last_accessed_at)->diffForHumans() }}
                                            @else
                                                <span class="text-slate-500">{{ __('New membership') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Channel Entry Form -->
                                <div class="mt-5 pt-3 border-t border-slate-800/60">
                                    <form method="POST" action="{{ route('channels.enter', $channel->id) }}">
                                        @csrf
                                        @if($canUsePinlessEntry)
                                            <button
                                                type="submit"
                                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium text-xs shadow-lg shadow-emerald-950/40 hover:shadow-emerald-900/40 transition-all flex items-center justify-center gap-2 cursor-pointer"
                                            >
                                                <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                <span>{{ __('Enter Channel (Pinless)') }}</span>
                                            </button>
                                        @else
                                            <button
                                                type="submit"
                                                class="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium text-xs border border-slate-700 hover:border-slate-600 transition-all flex items-center justify-center gap-2 cursor-pointer"
                                            >
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                <span>{{ __('Enter Channel (PIN Required)') }}</span>
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- Zero Channels State with Integrated Direct Redemption -->
                    <div class="rounded-2xl bg-slate-900/50 border border-dashed border-slate-800 p-8 sm:p-10 text-center space-y-6">
                        <div class="w-16 h-16 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-center text-slate-600 mx-auto">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>

                        <div class="max-w-md mx-auto space-y-1.5">
                            <h3 class="text-base font-semibold text-white tracking-tight">{{ __('No Channels Available') }}</h3>
                            <p class="text-xs text-slate-400">
                                {{ __('You currently have no enrolled channels. To join a channel, enter an invitation code below or ask a channel administrator to invite your email.') }}
                            </p>
                        </div>

                        <!-- Direct Inline Invite Code Redeemer -->
                        <form method="POST" action="{{ route('channels.redeem') }}" class="max-w-md mx-auto flex gap-2">
                            @csrf
                            <input
                                type="text"
                                name="code"
                                placeholder="INV-XXXX-XXXX"
                                required
                                class="flex-1 px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 placeholder-slate-600 text-xs font-mono tracking-widest uppercase focus:outline-none focus:border-emerald-500"
                            >
                            <button
                                type="submit"
                                class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-medium cursor-pointer transition-all shadow-md shadow-emerald-950/40 shrink-0 flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                <span>{{ __('Join Channel') }}</span>
                            </button>
                        </form>
                    </div>
                @endif

            </div>
        </div>

    </main>

    <!-- Modal: Redeem Invitation Code -->
    <div id="redeem-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden p-4 flex items-center justify-center">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden font-sans">
            <div class="flex items-center justify-between p-5 border-b border-slate-800 bg-slate-900/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">{{ __('Redeem Invitation Code') }}</h3>
                        <p class="text-[11px] text-slate-400">{{ __('Enter your channel invitation code') }}</p>
                    </div>
                </div>
                <button type="button" onclick="closeRedeemModal()" class="text-slate-400 hover:text-white cursor-pointer text-sm">✕</button>
            </div>

            <form method="POST" action="{{ route('channels.redeem') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label for="redeem-code-modal-input" class="block text-xs font-medium text-slate-300 mb-1.5 font-mono">
                        {{ __('Invitation Code') }} <span class="text-emerald-400">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="redeem-code-modal-input"
                        placeholder="INV-XXXX-XXXX"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 placeholder-slate-600 text-sm font-mono tracking-widest uppercase focus:outline-none focus:border-emerald-500/60 transition-colors"
                    >
                    <p class="text-[11px] text-slate-500 mt-1.5 font-mono">{{ __('Format: INV-XXXXXXXX or as provided by administrator') }}</p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        onclick="closeRedeemModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium cursor-pointer transition-colors"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-medium cursor-pointer transition-colors shadow-sm"
                    >
                        {{ __('Verify & Join Channel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-Side Javascript Handlers -->
    <script>
        function openRedeemModal() {
            const modal = document.getElementById('redeem-modal');
            modal.classList.remove('hidden');
            const input = document.getElementById('redeem-code-modal-input');
            if (input) input.focus();
        }

        function closeRedeemModal() {
            document.getElementById('redeem-modal').classList.add('hidden');
        }

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('profile-avatar-preview-img');
                    const initial = document.getElementById('profile-avatar-preview-initial');
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    initial.classList.add('hidden');
                    document.getElementById('remove-avatar-btn').classList.remove('hidden');
                    document.getElementById('remove-avatar-flag').value = '0';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function markAvatarForRemoval() {
            document.getElementById('avatar-file-input').value = '';
            const img = document.getElementById('profile-avatar-preview-img');
            img.src = '';
            img.classList.add('hidden');
            document.getElementById('profile-avatar-preview-initial').classList.remove('hidden');
            document.getElementById('remove-avatar-btn').classList.add('hidden');
            document.getElementById('remove-avatar-flag').value = '1';
        }

        async function saveProfile(e) {
            e.preventDefault();
            const form = document.getElementById('profile-form');
            const alert = document.getElementById('profile-alert');
            const btn = document.getElementById('profile-submit-btn');

            btn.disabled = true;
            btn.innerHTML = '<span>Saving...</span>';
            alert.classList.add('hidden');

            const formData = new FormData(form);

            try {
                const res = await fetch('{{ route("profile.update") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    alert.className = 'p-2.5 rounded-xl text-xs font-mono bg-emerald-950/60 border border-emerald-500/40 text-emerald-300';
                    alert.innerText = data.message || 'Profile saved successfully.';
                    alert.classList.remove('hidden');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    alert.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                    alert.innerText = data.message || 'Failed to update profile.';
                    alert.classList.remove('hidden');
                }
            } catch (err) {
                alert.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                alert.innerText = 'Network error while updating profile.';
                alert.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-3.5 h-3.5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg><span>Save Profile Settings</span>';
            }
        }

        async function initiateTwoFactorSetup() {
            try {
                const res = await fetch('{{ route("2fa.enable") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.qr_svg) {
                    document.getElementById('totp-qr-wrap').innerHTML = data.qr_svg;
                    document.getElementById('totp-secret-key').innerText = data.secret;
                    document.getElementById('totp-setup-container').classList.remove('hidden');
                }
            } catch(e) {
                window.alert('Error generating 2FA QR code');
            }
        }

        async function confirmTwoFactorSetup() {
            const code = document.getElementById('totp-confirm-code').value.trim();
            if (!code) return;
            try {
                const res = await fetch('{{ route("2fa.confirm") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ code: code })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                } else {
                    window.alert(data.message || 'Invalid code');
                }
            } catch (e) {
                window.alert('Network error confirming 2FA');
            }
        }

        async function disableTwoFactor() {
            if (!confirm('Are you sure you want to disable 2FA? Instant pinless entry to channels will be revoked.')) return;
            try {
                const res = await fetch('{{ route("2fa.disable") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                }
            } catch(e) {
                window.alert('Error disabling 2FA');
            }
        }

        async function registerPasskey() {
            try {
                const optRes = await fetch('{{ route("webauthn.register.options") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const options = await optRes.json();

                if (!navigator.credentials || !navigator.credentials.create) {
                    window.alert('Hardware biometrics / passkeys are not supported on this browser or device.');
                    return;
                }

                // Decode challenge & user ID
                options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
                options.user.id = new TextEncoder().encode(options.user.id);

                const credential = await navigator.credentials.create({ publicKey: options });

                const rawId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)))
                    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                const clientDataJSON = btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON)))
                    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                const attestationObject = btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject)))
                    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

                const keyName = prompt('Enter a label for this passkey (e.g. MacBook TouchID, iPhone):', 'Personal Passkey');

                const saveRes = await fetch('{{ route("webauthn.register") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: keyName || 'Hardware Passkey',
                        credential: {
                            id: credential.id,
                            rawId: rawId,
                            type: credential.type,
                            response: {
                                clientDataJSON: clientDataJSON,
                                attestationObject: attestationObject
                            }
                        }
                    })
                });

                const saveData = await saveRes.json();
                if (saveRes.ok && saveData.success) {
                    window.location.reload();
                } else {
                    window.alert(saveData.message || 'Failed to save passkey.');
                }
            } catch (err) {
                console.error(err);
                window.alert('Passkey registration was cancelled or failed.');
            }
        }

        async function deletePasskey(id) {
            if (!confirm('Remove this hardware passkey?')) return;
            try {
                const res = await fetch(`/webauthn/credentials/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                }
            } catch(e) {
                window.alert('Error deleting passkey.');
            }
        }

        async function saveRecoveryEmail() {
            const email = document.getElementById('recovery-email-input').value.trim();
            if (!email) return;

            try {
                const res = await fetch('{{ route("recovery.email.update") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ recovery_email: email })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.alert(data.message || 'Recovery email saved.');
                } else {
                    window.alert(data.message || 'Failed to save recovery email.');
                }
            } catch(e) {
                window.alert('Network error saving recovery email.');
            }
        }
    </script>
</body>
</html>
