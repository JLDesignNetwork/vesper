@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Logs Page Header Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-amber-950/30 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-amber-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>TELEMETRY TRANSMISSION & NETWORK AUDIT</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Transmission Logs') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400">
                    {{ $totalLogs ?? $recentVisitors->count() }} {{ __('Recorded Events') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Real-time connection audit feed capturing geographic ingress locations, IP addresses, frequency room handshakes, and operative authentication footprints.') }}
            </p>
        </div>

        <!-- Log Summary Metric Chips -->
        <div class="flex items-center gap-2.5 font-mono text-xs shrink-0 flex-wrap">
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-amber-400">📜</span>
                <span class="text-slate-400">{{ __('Live Window:') }}</span>
                <strong class="text-white">{{ $recentVisitors->count() }} {{ __('Entries') }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-emerald-400">🌐</span>
                <span class="text-slate-400">{{ __('Networks:') }}</span>
                <strong class="text-white">{{ $recentVisitors->pluck('ip_address')->unique()->count() }} {{ __('Unique IPs') }}</strong>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    @include('admin.partials.logs-section')
</div>
@endsection
