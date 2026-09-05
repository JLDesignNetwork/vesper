<!-- Registered Operatives Section -->
<div id="operatives-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden shadow-xl">
    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Registered Operatives') }}</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                    {{ $registeredUsers->count() }}
                </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('Encrypted roster of registered member profiles and security dossiers') }}</p>
        </div>
        <div class="flex items-center gap-2 font-mono text-xs text-slate-400">
            <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
            <span>{{ __('Level 5 Clearance') }}</span>
        </div>
    </div>

    <!-- Table of Users -->
    <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/60">
                    <th class="py-3 px-4 min-w-[200px]">{{ __('Operative') }}</th>
                    <th class="py-3 px-4 min-w-[180px]">{{ __('Network & Location') }}</th>
                    <th class="py-3 px-4 min-w-[150px]">{{ __('Demographics') }}</th>
                    <th class="py-3 px-4 min-w-[150px]">{{ __('Privacy & Security') }}</th>
                    <th class="py-3 px-4 min-w-[110px]">{{ __('Enrolled') }}</th>
                    <th class="py-3 px-4 text-right min-w-[140px] sticky right-0 bg-slate-950/90 backdrop-blur-md z-10 shadow-[-10px_0_12px_-4px_rgba(0,0,0,0.5)]">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                @forelse($registeredUsers as $regUser)
                    <tr class="hover:bg-white/[0.02] transition-colors group">
                        <!-- Col 1: Operative Profile & Contact -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden bg-gradient-to-br from-slate-700 to-slate-800 border border-slate-600 flex items-center justify-center font-mono font-bold text-xs text-emerald-400 shrink-0 shadow-md">
                                    @if($regUser->avatar_path)
                                        <img src="{{ $regUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                    @else
                                        {{ strtoupper(substr($regUser->name, 0, 2)) }}
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-white flex items-center gap-2">
                                        <span class="truncate">{{ $regUser->name }}</span>
                                        @if($regUser->isAdmin())
                                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 shrink-0">{{ __('ADMIN') }}</span>
                                        @else
                                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-800 border border-slate-700 text-slate-400 shrink-0">{{ __('MEMBER') }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400 mt-0.5">
                                        <span class="truncate max-w-[180px]" title="{{ $regUser->email }}">{{ $regUser->email }}</span>
                                        <button
                                            type="button"
                                            onclick="copyText('{{ $regUser->email }}', this, '{{ __('Email copied!') }}')"
                                            title="{{ __('Copy email') }}"
                                            class="p-0.5 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-300 transition-colors cursor-pointer shrink-0"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                        </button>
                                    </div>
                                    @if($regUser->bio)
                                        <div class="text-[10px] text-slate-500 truncate max-w-[200px] mt-0.5" title="{{ $regUser->bio }}">
                                            {{ $regUser->bio }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- Col 2: Network & Location -->
                        <td class="py-3.5 px-4 font-mono">
                            <div>
                                @if($regUser->hasGps())
                                    <div class="flex items-center gap-1.5" title="{{ __('Verified GPS: :lat, :lon', ['lat' => $regUser->latitude, 'lon' => $regUser->longitude]) }}">
                                        <span class="inline-flex items-center gap-1 text-emerald-400 bg-emerald-500/10 px-1.5 py-0.2 rounded border border-emerald-500/20 text-[10px] shrink-0">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            <span>{{ __('GPS') }}</span>
                                        </span>
                                        <span class="text-white font-medium text-xs truncate">{{ $regUser->city ?: $regUser->location }}</span>
                                        @if($regUser->country)
                                            <span class="text-slate-400 text-[10px]">({{ $regUser->country }})</span>
                                        @endif
                                    </div>
                                @elseif($regUser->location)
                                    <span class="text-slate-200 text-xs">{{ $regUser->location }}</span>
                                @else
                                    <span class="text-slate-500 text-[11px]">{{ __('Location Unverified') }}</span>
                                @endif

                                @if($regUser->hide_location)
                                    <span class="text-[9px] text-amber-400 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20 font-mono inline-block mt-0.5" title="{{ __('Location hidden from members') }}">
                                        🔒 {{ __('Private') }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center gap-1.5">
                                @if($regUser->latestIp())
                                    <span class="text-emerald-400 font-semibold text-[11px]">{{ $regUser->latestIp() }}</span>
                                    <button
                                        type="button"
                                        onclick="copyText('{{ $regUser->latestIp() }}', this, '{{ __('IP copied!') }}')"
                                        title="{{ __('Copy IP address') }}"
                                        class="p-0.5 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-300 transition-colors cursor-pointer"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    </button>
                                @else
                                    <span class="text-slate-500 text-[10px]">—</span>
                                @endif
                            </div>
                        </td>

                        <!-- Col 3: Demographics & Identity -->
                        <td class="py-3.5 px-4 font-mono text-xs">
                            <div class="flex items-center gap-2">
                                @if($regUser->age())
                                    <span class="text-emerald-400 font-semibold">{{ $regUser->age() }} {{ __('yrs') }}</span>
                                @endif
                                @if($regUser->gender)
                                    <span class="text-slate-300 notranslate" translate="no">· {{ __($regUser->gender) }}</span>
                                @endif
                                @if(!$regUser->age() && !$regUser->gender)
                                    <span class="text-slate-500 text-[11px]">—</span>
                                @endif
                            </div>
                            @if($regUser->birthday)
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    {{ $regUser->birthday->format('M d, Y') }}
                                </div>
                            @endif
                            @if($regUser->hide_age || $regUser->hide_birthday)
                                <span class="text-[9px] text-amber-400 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20 font-mono inline-block mt-0.5" title="{{ __('Hidden from members') }}">
                                    🔒 {{ __('Private') }}
                                </span>
                            @endif
                        </td>

                        <!-- Col 4: Privacy & Security Settings -->
                        <td class="py-3.5 px-4 font-mono">
                            <div class="space-y-1">
                                <div>
                                    @if($regUser->email_notifications)
                                        <span class="inline-flex items-center gap-1 text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 text-[10px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Alerts On') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700 text-[10px]">
                                            {{ __('Alerts Off') }}
                                        </span>
                                    @endif
                                </div>
                                @php
                                    $hiddenCount = ($regUser->hide_age ? 1 : 0) + ($regUser->hide_birthday ? 1 : 0) + ($regUser->hide_location ? 1 : 0) + ($regUser->hide_bio ? 1 : 0);
                                @endphp
                                <div class="flex items-center gap-1 flex-wrap">
                                    @if($hiddenCount > 0)
                                        <span class="inline-flex items-center gap-1 text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20 text-[10px] font-mono" title="{{ __('User has hidden :count profile field(s) from members', ['count' => $hiddenCount]) }}">
                                            <span>🔒</span>
                                            <span>{{ $hiddenCount }} {{ __('Private') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-slate-500 text-[10px] font-mono">
                                            {{ __('Public') }}
                                        </span>
                                    @endif

                                    @if($regUser->preferred_locale)
                                        <span class="inline-flex items-center gap-1 text-sky-400 bg-sky-500/10 px-1.5 py-0.5 rounded border border-sky-500/20 text-[10px] font-mono" title="{{ __('User preferred language override') }}">
                                            <span>🌐</span>
                                            <span>{{ strtoupper($regUser->preferred_locale) }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded border border-slate-700 text-[10px] font-mono" title="{{ __('Auto-detected from registered location') }}">
                                            <span>🌐</span>
                                            <span>{{ strtoupper($regUser->resolveLocationLocale()) }} (auto)</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- Col 5: Joined / Enrolled -->
                        <td class="py-3.5 px-4 font-mono text-slate-400 text-[11px] whitespace-nowrap">
                            {{ $regUser->created_at?->diffForHumans() ?? '—' }}
                        </td>

                        <!-- Col 6: Actions (Sticky Right Pin) -->
                        <td class="py-3.5 px-4 text-right sticky right-0 bg-slate-900/95 group-hover:bg-slate-900 backdrop-blur-md z-10 shadow-[-10px_0_12px_-4px_rgba(0,0,0,0.5)] whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono">
                                <button
                                    type="button"
                                    onclick='openUserDossier(@json($regUser))'
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors text-[11px] cursor-pointer"
                                >
                                    {{ __('Inspect') }}
                                </button>

                                @if($regUser->id === Auth::id())
                                    <button
                                        type="button"
                                        onclick="openProfileModal()"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition-colors text-[11px] cursor-pointer"
                                        title="{{ __('Edit My Profile') }}"
                                    >
                                        {{ __('Edit') }}
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.users.destroy', ['id' => $regUser->id]) }}" class="inline" onsubmit="return confirm('{{ __('Permanently purge this operative account?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 transition-colors text-[11px] cursor-pointer"
                                        >
                                            {{ __('Purge') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500 font-mono">
                            {{ __('No registered operatives found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
