@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Executive Command HQ Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/95 via-slate-900/80 to-emerald-950/40 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-emerald-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>EXECUTIVE COMMAND HQ & TELEMETRY</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Operations Overview') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                    {{ __('Level 5 Admin') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Unified tactical control console monitoring active encrypted frequencies, operative credentials, satellite radar telemetry, and connection streams.') }}
            </p>
        </div>

        <!-- Quick Jump Stations -->
        <div class="flex items-center gap-2 font-mono text-xs shrink-0 flex-wrap">
            <a
                href="{{ route('admin.channels.index') }}"
                class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 hover:border-cyan-500/40 text-cyan-300 transition-all flex items-center gap-2"
            >
                <span>📡</span>
                <span>{{ __('Channels Page') }}</span>
            </a>
            <a
                href="{{ route('admin.operatives.index') }}"
                class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 hover:border-teal-500/40 text-teal-300 transition-all flex items-center gap-2"
            >
                <span>👥</span>
                <span>{{ __('Operatives Page') }}</span>
            </a>
            <a
                href="{{ route('admin.intel.index') }}"
                class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 hover:border-indigo-500/40 text-indigo-300 transition-all flex items-center gap-2"
            >
                <span>🌐</span>
                <span>{{ __('Radar Page') }}</span>
            </a>
            <a
                href="{{ route('admin.logs.index') }}"
                class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 hover:border-amber-500/40 text-amber-300 transition-all flex items-center gap-2"
            >
                <span>📜</span>
                <span>{{ __('Logs Page') }}</span>
            </a>
        </div>
    </div>

    <!-- Quantitative Operational Metrics -->
    @include('admin.partials.metrics')

    <!-- Tactical Command Stations Matrix (Domain Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Station 1: Encrypted Channels -->
        <a
            href="{{ route('admin.channels.index') }}"
            class="p-5 rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-950/80 border border-slate-800 hover:border-cyan-500/50 transition-all group shadow-xl hover:shadow-cyan-950/20 flex flex-col justify-between"
        >
            <div>
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                        📡
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                        {{ $totalRooms }} {{ __('Channels') }}
                    </span>
                </div>
                <h3 class="text-sm font-semibold text-white mt-3 group-hover:text-cyan-300 transition-colors">{{ __('Encrypted Channels') }}</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ __('Full directory, PIN resets, translation filters, expiration quotas, and invite clearance codes.') }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono text-cyan-400">
                <span>{{ __('Enter Station') }}</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>

        <!-- Station 2: Operatives Intelligence -->
        <a
            href="{{ route('admin.operatives.index') }}"
            class="p-5 rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-950/80 border border-slate-800 hover:border-teal-500/50 transition-all group shadow-xl hover:shadow-teal-950/20 flex flex-col justify-between"
        >
            <div>
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-400 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                        👥
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-teal-500/10 border border-teal-500/20 text-teal-400">
                        {{ $totalUsers }} {{ __('Operatives') }}
                    </span>
                </div>
                <h3 class="text-sm font-semibold text-white mt-3 group-hover:text-teal-300 transition-colors">{{ __('Operatives Roster') }}</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ __('Personnel dossiers, IP unmasking, hardware biometrics enrollment, and access privilege control.') }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono text-teal-400">
                <span>{{ __('Enter Station') }}</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>

        <!-- Station 3: Global Satellite Radar -->
        <a
            href="{{ route('admin.intel.index') }}"
            class="p-5 rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-950/80 border border-slate-800 hover:border-indigo-500/50 transition-all group shadow-xl hover:shadow-indigo-950/20 flex flex-col justify-between"
        >
            <div>
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                        🌐
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        {{ $mapMarkers->count() }} {{ __('Nodes') }}
                    </span>
                </div>
                <h3 class="text-sm font-semibold text-white mt-3 group-hover:text-indigo-300 transition-colors">{{ __('Global Radar Map') }}</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ __('Full-screen geospatial Leaflet radar with dark carto tiles, GPS geolocation, and coordinate telemetry.') }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono text-indigo-400">
                <span>{{ __('Enter Station') }}</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>

        <!-- Station 4: Transmission Logs -->
        <a
            href="{{ route('admin.logs.index') }}"
            class="p-5 rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-950/80 border border-slate-800 hover:border-amber-500/50 transition-all group shadow-xl hover:shadow-amber-950/20 flex flex-col justify-between"
        >
            <div>
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
                        📜
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/20 text-amber-400">
                        {{ $totalVisitors }} {{ __('Audits') }}
                    </span>
                </div>
                <h3 class="text-sm font-semibold text-white mt-3 group-hover:text-amber-300 transition-colors">{{ __('Transmission Logs') }}</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ __('Audited connection telemetry, IP lookup, user-agent signatures, and handshake timestamps.') }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono text-amber-400">
                <span>{{ __('Enter Station') }}</span>
                <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>
    </div>

    <!-- Encrypted Frequency Directory & Management Grid -->
    @include('admin.partials.channels-section')

    <!-- Registered Operatives Intelligence Roster -->
    @include('admin.partials.operatives-section')

    <!-- Global Visitor Intelligence Satellite Map -->
    @include('admin.partials.intel-section')

    <!-- Real-time Transmission & Telemetry Logs -->
    @include('admin.partials.logs-section')

</div>
@endsection
