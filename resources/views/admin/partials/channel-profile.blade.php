{{-- Channel profile overview, loaded into the admin panel's channel profile modal. All values are escaped by Blade. --}}
<div class="space-y-5 text-xs" data-channel-profile="{{ $room->id }}">
    <div>
        <div class="flex flex-wrap items-baseline gap-2">
            <h4 class="text-base font-semibold text-white">{{ $room->title ?: $room->code }}</h4>
            <span class="font-mono text-slate-400">{{ $room->code }}</span>
            <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 font-mono text-emerald-300">{{ $room->type->value }}</span>
            <span class="px-2 py-0.5 rounded font-mono {{ $room->status === 'active' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300' : 'bg-slate-800 border border-slate-700 text-slate-400' }}">{{ $room->status === 'active' ? __('Active') : __('Archived') }}</span>
        </div>
        @if($room->description)
            <p class="mt-2 text-slate-300 whitespace-pre-line">{{ $room->description }}</p>
        @endif
    </div>

    <section aria-labelledby="channel-profile-settings-{{ $room->id }}">
        <h5 id="channel-profile-settings-{{ $room->id }}" class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-2">{{ __('Settings') }}</h5>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Type') }}</dt><dd class="text-slate-200 text-right">{{ __($room->type->label()) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Creator') }}</dt><dd class="text-slate-200 text-right">{{ $room->creator ? $room->creator->name.' @'.$room->creator->tag : ($room->is_system ? __('System') : '—') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Created') }}</dt><dd class="text-slate-200 text-right">{{ $room->created_at?->toDayDateTimeString() }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Expires') }}</dt><dd class="text-slate-200 text-right">{{ $room->expires_at?->toDayDateTimeString() ?? __('Never') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('PIN') }}</dt><dd class="text-slate-200 text-right">{{ $room->passcode_hash ? __('Set (stored as a hash)') : __('None') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Burn after reading') }}</dt><dd class="text-slate-200 text-right">{{ $room->burn_after_reading ? __('On') : __('Off') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Admin notifications') }}</dt><dd class="text-slate-200 text-right">{{ $room->notify_admin ? __('On') : __('Off') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Messages') }}</dt><dd class="text-slate-200 text-right font-mono">{{ $room->messages_count }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Pinned messages') }}</dt><dd class="text-slate-200 text-right font-mono">{{ $room->pins_count }} / {{ \App\Models\MessagePin::MAX_PER_CHANNEL }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Translations') }}</dt><dd class="text-slate-200 text-right font-mono uppercase">{{ implode(' ', $room->effectiveAllowedLanguages()) }}</dd></div>
            @if($showsGeolocation && $room->created_by_ip)
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Created from IP') }}</dt><dd class="text-slate-200 text-right font-mono">{{ $room->created_by_ip }}</dd></div>
            @endif
        </dl>
    </section>

    <section aria-labelledby="channel-profile-members-{{ $room->id }}">
        <h5 id="channel-profile-members-{{ $room->id }}" class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-2">{{ __('Members') }} <span class="text-slate-500">({{ $members->count() }})</span></h5>
        @forelse($members as $member)
            <div class="channel-profile-member py-1.5 flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-800/60">
                <span><span class="text-slate-200">{{ $member->name }}</span> <span class="font-mono text-emerald-300">{{ '@'.$member->tag }}</span> @if($member->role === 'owner')<span class="text-[10px] text-amber-200">{{ __('creator') }}</span>@endif</span>
                <span class="font-mono text-[10px] text-slate-500">{{ __('Joined :when', ['when' => \Illuminate\Support\Carbon::parse($member->joined_at)->diffForHumans()]) }}@if($member->last_accessed_at) · {{ __('last opened :when', ['when' => \Illuminate\Support\Carbon::parse($member->last_accessed_at)->diffForHumans()]) }}@endif</span>
            </div>
        @empty
            <p class="text-slate-500">{{ __('No members yet.') }}</p>
        @endforelse
    </section>

    <section aria-labelledby="channel-profile-invites-{{ $room->id }}">
        <h5 id="channel-profile-invites-{{ $room->id }}" class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-2">{{ __('Invited, not yet accepted') }} <span class="text-slate-500">({{ $pendingInvitees->count() }})</span></h5>
        @forelse($pendingInvitees as $invitee)
            <div class="channel-profile-invitee py-1.5 flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-800/60">
                <span><span class="text-slate-200">{{ $invitee->name }}</span> <span class="font-mono text-emerald-300">{{ '@'.$invitee->tag }}</span></span>
                <span class="font-mono text-[10px] text-slate-500">{{ $invitee->invited_by_name ? __('Invited by :name', ['name' => $invitee->invited_by_name]) : '' }} · {{ \Illuminate\Support\Carbon::parse($invitee->joined_at)->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-slate-500">{{ __('No pending invitations.') }}</p>
        @endforelse
        @if($inviteLinks->isNotEmpty())
            <p class="mt-3 mb-1 text-slate-400">{{ __('Open invitation links and codes') }}</p>
            @foreach($inviteLinks as $invitation)
                <div class="py-1 flex flex-wrap justify-between gap-2 font-mono text-[11px]">
                    <span class="text-slate-200">{{ $invitation->code }}</span>
                    <span class="text-slate-500">{{ __(':used of :max uses', ['used' => $invitation->uses_count, 'max' => $invitation->max_uses ?: '∞']) }} · {{ $invitation->expires_at ? __('expires :when', ['when' => $invitation->expires_at->diffForHumans()]) : __('no expiry') }}@if($invitation->creator) · {{ $invitation->creator->name }}@endif</span>
                </div>
            @endforeach
        @endif
        @if($declinedInvitees->isNotEmpty())
            <p class="mt-3 text-slate-500">{{ trans_choice(':count invitation declined|:count invitations declined', $declinedInvitees->count(), ['count' => $declinedInvitees->count()]) }}</p>
        @endif
    </section>

    @if($removedMembers->isNotEmpty())
        <section aria-labelledby="channel-profile-removed-{{ $room->id }}">
            <h5 id="channel-profile-removed-{{ $room->id }}" class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-2">{{ __('Removed members') }} <span class="text-slate-500">({{ $removedMembers->count() }})</span></h5>
            @foreach($removedMembers as $removed)
                <div class="channel-profile-removed py-1.5 flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-800/60">
                    <span><span class="text-slate-200">{{ $removed->name }}</span> <span class="font-mono text-emerald-300">{{ '@'.$removed->tag }}</span></span>
                    <span class="font-mono text-[10px] text-slate-500">{{ $removed->kicked_at ? __('Removed :when', ['when' => \Illuminate\Support\Carbon::parse($removed->kicked_at)->diffForHumans()]) : '' }}@if($removed->kicked_by_name) · {{ $removed->kicked_by_name }}@endif</span>
                </div>
            @endforeach
        </section>
    @endif
</div>
