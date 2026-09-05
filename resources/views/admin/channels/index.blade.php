@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Channels Page Header Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-cyan-950/30 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-cyan-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                <span>{{ __('Frequency Directory & Security Protocols') }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Encrypted Channels') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    {{ $rooms->count() }} {{ __('Total') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Manage private frequency nodes, configure zero-discovery authorization PINs, monitor message quotas, and generate time-expiring clearance tokens.') }}
            </p>
        </div>

        <!-- Quick Summary Chips -->
        <div class="flex items-center gap-2.5 font-mono text-xs shrink-0 flex-wrap">
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-emerald-400">●</span>
                <span class="text-slate-400">{{ __('Active:') }}</span>
                <strong class="text-white">{{ $activeRooms ?? $rooms->where('status', 'active')->count() }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-slate-500">●</span>
                <span class="text-slate-400">{{ __('Archived:') }}</span>
                <strong class="text-white">{{ $archivedRooms ?? $rooms->where('status', 'archived')->count() }}</strong>
            </div>
            <button
                type="button"
                onclick="openCreateModal()"
                class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg shadow-emerald-950/40 transition-all cursor-pointer flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('Establish Frequency') }}</span>
            </button>
        </div>
    </div>

    <!-- Channels Management Directory Table -->
    @include('admin.partials.channels-section')
</div>
@endsection
