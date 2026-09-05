@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Intel Page Header Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-indigo-950/30 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-indigo-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                <span>{{ __('Global Geospatial Satellite Radar') }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Global Intelligence') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400">
                    {{ $totalNodes ?? $mapMarkers->count() }} {{ __('Active Nodes') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Real-time geospatial radar visualizing verified GPS coordinates of registered operatives and geographic origins of incoming transmission traffic.') }}
            </p>
        </div>

        <!-- Node Metrics & Actions -->
        <div class="flex items-center gap-2.5 font-mono text-xs shrink-0 flex-wrap">
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-purple-400">📍</span>
                <span class="text-slate-400">{{ __('Operative Nodes:') }}</span>
                <strong class="text-white">{{ $registeredNodes ?? $registeredUsers->filter(fn ($u) => $u->hasGps())->count() }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-emerald-400">🌐</span>
                <span class="text-slate-400">{{ __('Visitor Nodes:') }}</span>
                <strong class="text-white">{{ $visitorNodes ?? $mapMarkers->where('is_user', false)->count() }}</strong>
            </div>
            <button
                type="button"
                id="admin-gps-sync-btn"
                onclick="syncAdminGps()"
                class="px-4 py-2 rounded-xl bg-cyan-950/50 hover:bg-cyan-900/50 border border-cyan-500/40 text-cyan-300 transition-all flex items-center gap-2 cursor-pointer shadow-[0_0_15px_rgba(6,182,212,0.15)]"
                title="{{ __('Synchronize high-precision GPS coordinates from your browser') }}"
            >
                <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span id="admin-gps-btn-text">{{ __('SYNC GPS') }}</span>
            </button>
        </div>
    </div>

    <!-- Global Satellite Radar Leaflet Map Container -->
    <div id="intel-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden shadow-2xl">
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between flex-wrap gap-2 text-xs font-mono">
            <div class="flex items-center gap-2">
                <span class="text-slate-400 uppercase tracking-wider text-[11px]">{{ __('Telemetry Status:') }}</span>
                <span class="text-emerald-400 font-semibold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('Satellite radar feed active') }}
                </span>
            </div>
            <div class="flex items-center gap-4 text-slate-400 text-[11px]">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> {{ __('Registered Operative') }}</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __('Transmission Node') }}</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span> {{ __('Your Position') }}</span>
            </div>
        </div>
        <div id="admin-map" class="h-[520px] w-full"></div>
    </div>

    <!-- Mapped Geospatial Nodes Grid -->
    <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Geolocated Node Telemetry') }}</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                    {{ $mapMarkers->count() }}
                </span>
            </h3>
            <span class="text-xs text-slate-500 font-mono">{{ __('Sorted by latest activity') }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($mapMarkers->take(12) as $marker)
                <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 hover:border-slate-700 transition-colors flex items-start gap-3">
                    <div class="text-2xl shrink-0">{{ $marker['flag'] ?? '📍' }}</div>
                    <div class="min-w-0 flex-1 font-sans text-xs">
                        <div class="flex items-center justify-between gap-1">
                            <span class="font-semibold text-white truncate">{{ $marker['alias'] }}</span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded {{ $marker['is_user'] ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-slate-800 text-slate-400' }}">
                                {{ $marker['is_user'] ? __('OPERATIVE') : __('TRAFFIC') }}
                            </span>
                        </div>
                        <div class="text-slate-400 text-[11px] truncate mt-0.5">
                            {{ $marker['city'] }}, {{ $marker['country'] }}
                        </div>
                        <div class="flex items-center justify-between text-[10px] font-mono text-slate-500 mt-2 pt-2 border-t border-slate-800/60">
                            <span>{{ round($marker['latitude'], 4) }}, {{ round($marker['longitude'], 4) }}</span>
                            <span>{{ $marker['last_seen_human'] }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-slate-500 font-mono text-xs">
                    {{ __('No geolocated nodes currently mapped.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
