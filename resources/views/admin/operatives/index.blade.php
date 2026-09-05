@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Operatives Page Header Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-teal-950/30 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-teal-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                <span>OPERATIVE ROSTER & IDENTITY INTELLIGENCE</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Registered Operatives') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-400">
                    {{ $registeredUsers->count() }} {{ __('Total') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Inspect operative intelligence dossiers, unmask privacy boundaries, monitor hardware biometric authentication enrollments, and manage personnel clearances.') }}
            </p>
        </div>

        <!-- Operative Metric Chips -->
        <div class="flex items-center gap-2.5 font-mono text-xs shrink-0 flex-wrap">
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-emerald-400">🛡️</span>
                <span class="text-slate-400">{{ __('Admins:') }}</span>
                <strong class="text-white">{{ $adminCount ?? $registeredUsers->where('role', 'admin')->count() }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-cyan-400">👤</span>
                <span class="text-slate-400">{{ __('Members:') }}</span>
                <strong class="text-white">{{ $memberCount ?? $registeredUsers->where('role', 'member')->count() }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-purple-400">📍</span>
                <span class="text-slate-400">{{ __('GPS Nodes:') }}</span>
                <strong class="text-white">{{ $gpsVerifiedCount ?? $registeredUsers->filter(fn ($u) => $u->hasGps())->count() }}</strong>
            </div>
        </div>
    </div>

    <!-- Operatives Roster Table -->
    @include('admin.partials.operatives-section')
</div>
@endsection
