<!-- Metric Stat Cards Telemetry -->
<div id="overview-section" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <!-- Active Channels -->
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md relative overflow-hidden group hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
            <span>{{ __('Active Channels') }}</span>
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight">
            {{ $activeRooms }} <span class="text-xs text-slate-500 font-normal">/ {{ $totalRooms }}</span>
        </div>
        <div class="text-[10px] text-slate-500 font-mono mt-1">
            {{ __('Private encrypted channels') }}
        </div>
    </div>

    <!-- Registered Members -->
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md group hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
            <span>{{ __('Registered Members') }}</span>
            <span class="text-teal-400 text-[10px]">AUTH</span>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight">
            {{ number_format($totalUsers) }}
        </div>
        <div class="text-[10px] text-slate-500 font-mono mt-1">
            {{ __('Verified active members') }}
        </div>
    </div>

    <!-- Total Messages -->
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md group hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
            <span>{{ __('Total Messages') }}</span>
            <span class="text-cyan-400 text-[10px]">MSGS</span>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight">
            {{ number_format($totalMessages) }}
        </div>
        <div class="text-[10px] text-slate-500 font-mono mt-1">
            {{ __('Secure exchanged messages') }}
        </div>
    </div>

    <!-- Connection Audits -->
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md group hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
            <span>{{ __('Connection Audits') }}</span>
            <span class="text-amber-400 text-[10px]">AUDIT</span>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight">
            {{ number_format($totalVisitors) }}
        </div>
        <div class="text-[10px] text-slate-500 font-mono mt-1">
            {{ __('Logged access requests') }}
        </div>
    </div>

    <!-- Storage Used -->
    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md group hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
            <span>{{ __('Storage Used') }}</span>
            <span class="text-indigo-400 text-[10px]">DISK</span>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight">
            {{ $formattedStorage }}
        </div>
        <div class="text-[10px] text-slate-500 font-mono mt-1">
            {{ __('Encrypted attachments & media') }}
        </div>
    </div>
</div>
