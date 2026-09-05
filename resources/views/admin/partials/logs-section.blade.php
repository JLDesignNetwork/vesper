<!-- Recent Visitors & Telemetry Logs Section -->
<div id="logs-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Recent Visitors & Telemetry Logs') }}</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                    {{ $recentVisitors->count() }}
                </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('Audited connection telemetry and visitor network details.') }}</p>
        </div>
        <div class="font-mono text-xs text-slate-400">
            {{ __('Last 50 recorded entries') }}
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/40">
                    <th class="py-3 px-4">{{ __('Visitor') }}</th>
                    <th class="py-3 px-4">{{ __('Location') }}</th>
                    <th class="py-3 px-4">{{ __('IP Address') }}</th>
                    <th class="py-3 px-4">{{ __('Channel') }}</th>
                    <th class="py-3 px-4">{{ __('Last Active') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                @forelse($recentVisitors as $v)
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="py-3 px-4 font-mono text-slate-200">
                            {{ $v['alias'] }}
                        </td>
                        <td class="py-3 px-4">
                            <span class="mr-1.5">{{ $v['flag'] }}</span>
                            <span class="text-slate-300">{{ $v['city'] }}, {{ $v['country'] }}</span>
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">
                            {{ $v['ip_address'] }}
                        </td>
                        <td class="py-3 px-4 font-mono text-emerald-400">
                            {{ $v['room_code'] }}
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">
                            {{ $v['last_seen_human'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-500 font-mono">
                            {{ __('No visitors recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
