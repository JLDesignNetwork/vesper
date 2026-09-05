<!-- Global Traffic & Geospatial Satellite Radar Map -->
<div id="intel-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
    <div class="p-5 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Global Satellite Radar & Geospatial Intel') }}</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('Real-time geospatial distribution of operative nodes and channel traffic.') }}</p>
        </div>
        <div class="flex items-center gap-2 font-mono text-xs">
            <button
                type="button"
                id="admin-gps-sync-btn"
                onclick="syncAdminGps()"
                class="px-3.5 py-1.5 rounded-xl bg-cyan-950/50 hover:bg-cyan-900/50 border border-cyan-500/40 text-cyan-300 transition-all flex items-center gap-2 cursor-pointer shadow-[0_0_15px_rgba(6,182,212,0.15)]"
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
    <div id="admin-map" class="h-96 w-full"></div>
</div>
