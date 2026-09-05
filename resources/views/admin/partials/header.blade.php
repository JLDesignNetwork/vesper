<header class="w-full border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-xl sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">

        <!-- 1. Brand Identity & Live Telemetry Lockup -->
        <div class="flex items-center gap-3.5 shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 via-teal-500/20 to-cyan-500/20 border border-emerald-500/30 group-hover:border-emerald-500/50 flex items-center justify-center shadow-lg shadow-emerald-950/40 transition-all">
                    <svg class="w-5 h-5 text-emerald-400 group-hover:scale-105 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                        <span>{{ __('Vesper') }}</span>
                        <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 tracking-wider">Ghostwire</span>
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-emerald-400/90 font-medium tracking-wide">ONLINE</span>
                        <span class="text-slate-600">|</span>
                        <span class="text-slate-400">CMD CENTER</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- 2. Primary Executive Navigation Menu (Desktop) -->
        <nav class="hidden lg:flex items-center gap-1 p-1 bg-slate-900/80 border border-slate-800 rounded-xl font-mono text-xs shadow-inner">
            <a
                href="#overview-section"
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                <span>{{ __('Overview') }}</span>
            </a>
            <a
                href="#channels-section"
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                <span>{{ __('Channels') }}</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700">{{ $totalRooms ?? 0 }}</span>
            </a>
            <a
                href="#operatives-section"
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                <span>{{ __('Operatives') }}</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700">{{ $totalUsers ?? 0 }}</span>
            </a>
            <a
                href="#intel-section"
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ __('Global Intel') }}</span>
            </a>
            <a
                href="#logs-section"
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>{{ __('Logs') }}</span>
            </a>
        </nav>

        <!-- 3. Executive Control Matrix (Right) -->
        <div class="flex items-center gap-2.5 font-mono text-xs">

            <!-- Satellite GPS Status Pill with 1-Click Sync -->
            <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-300">
                <span id="gps-status-flag" class="text-sm">{{ $adminUser->country_code ? app(\App\Services\GeoLocationService::class)->countryCodeToFlag($adminUser->country_code) : '📍' }}</span>
                <span id="gps-status-location" class="text-[11px] max-w-[130px] truncate font-sans text-slate-200">
                    {{ $adminUser->city ?: ($adminUser->location ?: __('GPS Unsynced')) }}
                </span>
                <button
                    type="button"
                    onclick="syncAdminGps(this)"
                    id="gps-sync-header-btn"
                    title="{{ __('Synchronize Satellite GPS Coordinates') }}"
                    class="ml-1 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </button>
            </div>

            <!-- Quick Action: Create Channel -->
            <button
                type="button"
                onclick="openCreateModal()"
                class="hidden sm:flex px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-sans font-medium text-xs shadow-lg shadow-emerald-950/40 hover:shadow-emerald-900/40 transition-all items-center gap-1.5 cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('New Channel') }}</span>
            </button>

            <!-- Admin Profile & Menu Dropdown -->
            <div class="relative" id="admin-user-menu-wrap">
                <button
                    type="button"
                    onclick="toggleAdminUserMenu()"
                    class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs flex items-center gap-2 transition-colors cursor-pointer"
                    title="{{ __('Command Identity & Settings') }}"
                >
                    <div class="w-6 h-6 rounded-full overflow-hidden bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold text-[10px] shrink-0">
                        @if($adminUser->avatar_path)
                            <img id="header-avatar-img" src="{{ $adminUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                            <span id="header-avatar-initial" class="hidden">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                        @else
                            <img id="header-avatar-img" src="" class="w-full h-full object-cover hidden" alt="">
                            <span id="header-avatar-initial">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <span id="header-user-name" class="font-sans font-medium text-xs max-w-[110px] truncate hidden sm:inline">{{ $adminUser->name }}</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>

                <!-- Floating Dropdown Card -->
                <div
                    id="admin-user-dropdown"
                    class="hidden absolute right-0 mt-2 w-64 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-2 z-50 font-sans space-y-1 backdrop-blur-xl"
                >
                    <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800/80 mb-2">
                        <div class="font-semibold text-white text-xs truncate">{{ $adminUser->name }}</div>
                        <div class="text-[11px] text-slate-400 truncate">{{ $adminUser->email }}</div>
                        <div class="mt-1.5 flex items-center gap-1.5">
                            <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">ADMINISTRATOR</span>
                            @if($adminUser->hasTwoFactor() || $adminUser->hasBiometrics())
                                <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">2FA SECURED</span>
                            @endif
                        </div>
                    </div>

                    <!-- Jump to Operative Channels Hub -->
                    <a
                        href="{{ route('channels.index') }}"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-slate-300 hover:text-white hover:bg-slate-800 transition-colors"
                    >
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        <span>{{ __('Operative Channels Hub') }}</span>
                    </a>

                    <!-- Profile & Security Modal Trigger -->
                    <button
                        type="button"
                        onclick="toggleAdminUserMenu(); openProfileModal();"
                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-slate-300 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <span>{{ __('Profile & Security Settings') }}</span>
                    </button>

                    <!-- Language Switcher Submenu -->
                    <div class="px-3 py-2 border-t border-slate-800/80">
                        <div class="text-[10px] font-mono text-slate-500 uppercase tracking-wider mb-1.5">{{ __('Interface Language') }}</div>
                        <div class="grid grid-cols-4 gap-1 text-center font-mono text-[11px]">
                            <a href="{{ route('locale.switch', 'en') }}" class="py-1 rounded bg-slate-950 border border-slate-800 hover:border-emerald-500/50 {{ app()->getLocale() === 'en' ? 'text-emerald-400 border-emerald-500/50' : 'text-slate-400' }}">EN</a>
                            <a href="{{ route('locale.switch', 'ru') }}" class="py-1 rounded bg-slate-950 border border-slate-800 hover:border-emerald-500/50 {{ app()->getLocale() === 'ru' ? 'text-emerald-400 border-emerald-500/50' : 'text-slate-400' }}">RU</a>
                            <a href="{{ route('locale.switch', 'fr') }}" class="py-1 rounded bg-slate-950 border border-slate-800 hover:border-emerald-500/50 {{ app()->getLocale() === 'fr' ? 'text-emerald-400 border-emerald-500/50' : 'text-slate-400' }}">FR</a>
                            <a href="{{ route('locale.switch', 'it') }}" class="py-1 rounded bg-slate-950 border border-slate-800 hover:border-emerald-500/50 {{ app()->getLocale() === 'it' ? 'text-emerald-400 border-emerald-500/50' : 'text-slate-400' }}">IT</a>
                        </div>
                    </div>

                    <!-- Sign Out Form -->
                    <form method="POST" action="{{ route('logout') }}" class="pt-1 border-t border-slate-800/80">
                        @csrf
                        <button
                            type="submit"
                            class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            <span>{{ __('Disengage & Sign Out') }}</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mobile Hamburger Button -->
            <button
                type="button"
                onclick="toggleMobileAdminMenu()"
                class="lg:hidden p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 transition-colors cursor-pointer"
                title="{{ __('Toggle Command Navigation') }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
            </button>
        </div>
    </div>

    <!-- Mobile Slide-Down Command Drawer -->
    <div id="mobile-admin-drawer" class="hidden lg:hidden border-t border-slate-800/80 bg-slate-950/95 backdrop-blur-2xl px-4 py-4 space-y-3 font-mono text-xs">
        <div class="grid grid-cols-2 gap-2">
            <a href="#overview-section" onclick="toggleMobileAdminMenu()" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 flex items-center gap-2">
                <span class="text-emerald-400">📊</span>
                <span>{{ __('Overview') }}</span>
            </a>
            <a href="#channels-section" onclick="toggleMobileAdminMenu()" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 flex items-center gap-2">
                <span class="text-cyan-400">📡</span>
                <span>{{ __('Channels') }}</span>
            </a>
            <a href="#operatives-section" onclick="toggleMobileAdminMenu()" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 flex items-center gap-2">
                <span class="text-teal-400">👥</span>
                <span>{{ __('Operatives') }}</span>
            </a>
            <a href="#intel-section" onclick="toggleMobileAdminMenu()" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 flex items-center gap-2">
                <span class="text-indigo-400">🌐</span>
                <span>{{ __('Intel Map') }}</span>
            </a>
        </div>

        <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
            <button
                type="button"
                onclick="toggleMobileAdminMenu(); openCreateModal();"
                class="flex-1 py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-sans font-medium text-xs flex items-center justify-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('Create Channel') }}</span>
            </button>
        </div>
    </div>
</header>
