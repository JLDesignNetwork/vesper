<!-- Channels Directory & Operations Section -->
<div id="channels-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Channels Directory') }}</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                    {{ $rooms->count() }}
                </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('Encrypted communication channels and access controls') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('createConsoleChannels')
            <button
                type="button"
                onclick="openCreateModal()"
                class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-mono flex items-center gap-1.5 cursor-pointer transition-all shadow-md shadow-emerald-950/30"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('Create Channel') }}</span>
            </button>
            @else
                <a href="{{ route('profile.show') }}" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-mono">{{ __('Create a channel from your profile') }}</a>
            @endcan
        </div>
    </div>

    <!-- Table of Channels -->
    <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/60">
                    <th class="py-3 px-4 min-w-[160px]">{{ __('Channel') }}</th>
                    <th class="py-3 px-4 min-w-[120px]">{{ __('Type') }}</th>
                    <th class="py-3 px-4 min-w-[140px]">{{ __('Creator') }}</th>
                    <th class="py-3 px-4 min-w-[90px]">{{ __('Members') }}</th>
                    <th class="py-3 px-4 min-w-[130px]">{{ __('Translations') }}</th>
                    <th class="py-3 px-4 min-w-[110px]">{{ __('Status') }}</th>
                    <th class="py-3 px-4 min-w-[110px]">{{ __('Notifications') }}</th>
                    <th class="py-3 px-4 min-w-[90px]">{{ __('Messages') }}</th>
                    <th class="py-3 px-4 min-w-[150px]">{{ __('Direct Invite Link') }}</th>
                    <th class="py-3 px-4 text-right min-w-[220px] sticky right-0 bg-slate-950/90 backdrop-blur-md z-10 shadow-[-10px_0_12px_-4px_rgba(0,0,0,0.5)]">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                @forelse($rooms as $room)
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-semibold text-white flex items-center gap-2">
                                <span>{{ $room->title ?: $room->code }}</span>
                                @if($room->burn_after_reading)
                                    <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-300">
                                        {{ __('EXPIRES') }}
                                    </span>
                                @endif
                            </div>
                            <div class="font-mono text-[11px] text-slate-400 mt-0.5">
                                {{ $room->code }}
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-emerald-300 text-[11px]">{{ $room->type->value }}</span>
                            <div class="text-[10px] text-slate-500 mt-1">{{ __($room->type->label()) }}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            @if($room->creator)
                                <div class="text-slate-200">{{ $room->creator->name }}</div>
                                <div class="font-mono text-[10px] text-emerald-300">{{ '@'.$room->creator->tag }}</div>
                            @else
                                <span class="text-slate-500">{{ $room->is_system ? __('System') : '—' }}</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">
                            {{ $room->members_count ?? '—' }}
                            @if(($room->pending_invites_count ?? 0) > 0)
                                <span class="text-[10px] text-slate-500">· {{ trans_choice(':count link|:count links', $room->pending_invites_count, ['count' => $room->pending_invites_count]) }}</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            <div class="flex items-center gap-1 flex-wrap">
                                @foreach($room->effectiveAllowedLanguages() as $lang)
                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 text-[10px] uppercase font-bold">
                                        {{ strtoupper($lang) }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            @if($room->status === 'active')
                                <span class="inline-flex items-center gap-1 text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700 text-[10px]">
                                    {{ __('Archived') }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            @can('update', $room)
                            <form method="POST" action="{{ route('admin.channels.toggle-notifications', ['id' => $room->id]) }}" class="inline">
                                @csrf
                                <button
                                    type="submit"
                                    title="{{ $room->notify_admin ? __('Admin alerts enabled. Click to disable.') : __('Admin alerts disabled. Click to enable.') }}"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-sans transition-colors cursor-pointer {{ $room->notify_admin ? 'text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 hover:bg-emerald-500/20' : 'text-slate-400 bg-slate-900 border border-slate-800 hover:text-slate-200 hover:border-slate-700' }}"
                                >
                                    <svg class="w-3.5 h-3.5 {{ $room->notify_admin ? 'text-emerald-400' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span>{{ $room->notify_admin ? __('Enabled') : __('Disabled') }}</span>
                                </button>
                            </form>
                            @else
                                <span class="text-[11px] {{ $room->notify_admin ? 'text-emerald-300' : 'text-slate-400' }}">{{ $room->notify_admin ? __('Enabled') : __('Disabled') }}</span>
                            @endcan
                        </td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">
                            {{ $room->messages_count }}
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onclick="copyChannelLink('{{ $room->code }}', this)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-emerald-500/40 text-slate-300 hover:text-emerald-300 transition-colors flex items-center gap-1.5 cursor-pointer text-[11px]"
                                    title="{{ __('Copy channel invitation link') }}"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    <span>{{ __('Copy Link') }}</span>
                                </button>

                            </div>
                            <div class="text-[10px] font-mono text-slate-500 mt-1 truncate max-w-[180px]" title="/c/{{ $room->code }}">
                                /c/{{ $room->code }}
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right sticky right-0 bg-slate-900/95 group-hover:bg-slate-900 backdrop-blur-md z-10 shadow-[-10px_0_12px_-4px_rgba(0,0,0,0.5)] whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono">
                                <button
                                    type="button"
                                    data-channel-profile-url="{{ route('admin.channels.profile', ['id' => $room->id]) }}"
                                    class="channel-profile-btn px-2.5 py-1 rounded-lg bg-violet-500/10 hover:bg-violet-500/20 text-violet-200 border border-violet-500/30 transition-colors text-[11px] cursor-pointer"
                                >
                                    {{ __('Profile') }}
                                </button>

                                @can('update', $room)
                                <button
                                    type="button"
                                    onclick="openEditModal('{{ $room->id }}', '{{ $room->code }}', '{{ addslashes($room->title ?? '') }}', '', {{ json_encode($room->allowed_languages ?? \App\Services\LanguageService::codes()) }}, '{{ $room->status }}', {{ $room->notify_admin ? 'true' : 'false' }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors text-[11px] cursor-pointer"
                                >
                                    {{ __('Edit') }}
                                </button>

                                <button
                                    type="button"
                                    onclick="openInviteModal('{{ $room->id }}', '{{ $room->code }}', '{{ addslashes($room->title ?: $room->code) }}', '')"
                                    class="px-2.5 py-1 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 transition-colors text-[11px] cursor-pointer"
                                >
                                    {{ __('Invite') }}
                                </button>
                                @endcan

                                <a
                                    href="{{ route('rooms.show', ['room' => $room->code]) }}"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition-colors text-[11px]"
                                >
                                    {{ __('Enter') }}
                                </a>

                                @can('update', $room)
                                <form method="POST" action="{{ route('admin.channels.toggle', ['id' => $room->id]) }}" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors text-[11px] cursor-pointer"
                                    >
                                        {{ $room->status === 'active' ? __('Archive') : __('Activate') }}
                                    </button>
                                </form>
                                @endcan

                                @can('destroy', $room)
                                <form method="POST" action="{{ route('admin.channels.destroy', ['id' => $room->id]) }}" class="inline" onsubmit="return confirm({{ Js::from(__('Are you sure you want to permanently erase this channel and all its data?')) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:rose-500/20 text-rose-300 border border-rose-500/30 transition-colors text-[11px] cursor-pointer"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-slate-500 font-mono">
                            {{ __('No channels exist yet. Click "Create Channel" to create your first secure channel.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
