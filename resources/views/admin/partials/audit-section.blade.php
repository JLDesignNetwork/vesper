<!-- Admin Audit Log (entries the viewer may read) -->
<div id="audit-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
    <div class="p-5 border-b border-slate-800/80">
        <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
            <span>{{ __('Audit Log') }}</span>
            <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">{{ $auditEntries->count() }}</span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">{{ __('Rank changes, kicks, reports, ban votes and bans. Most recent first.') }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/40">
                    <th class="py-3 px-4">{{ __('Action') }}</th>
                    <th class="py-3 px-4">{{ __('By') }}</th>
                    <th class="py-3 px-4">{{ __('Member') }}</th>
                    <th class="py-3 px-4">{{ __('Channel') }}</th>
                    <th class="py-3 px-4">{{ __('When') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                @foreach($auditEntries as $entry)
                    <tr class="audit-entry">
                        <td class="py-3 px-4 font-mono text-amber-200">{{ $entry->action->label() }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $entry->actor ? $entry->actor->name.' @'.$entry->actor->tag : __('System') }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $entry->subject ? $entry->subject->name.' @'.$entry->subject->tag : '—' }}</td>
                        <td class="py-3 px-4 font-mono text-emerald-400">{{ $entry->room?->code ?? '—' }}</td>
                        <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">{{ $entry->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
