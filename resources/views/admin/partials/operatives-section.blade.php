<!-- Registered Operatives Section -->
<div id="operatives-section" class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
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
            <span class="w-2 h-2 rounded-full bg-teal-400"></span>
            <span>{{ __('Level 5 Clearance') }}</span>
        </div>
    </div>

    <!-- Table of Users -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/40">
                    <th class="py-3 px-4">{{ __('User') }}</th>
                    <th class="py-3 px-4">{{ __('Email') }}</th>
                    <th class="py-3 px-4">{{ __('IP Address') }}</th>
                    <th class="py-3 px-4">{{ __('Age & Birthday') }}</th>
                    <th class="py-3 px-4">{{ __('Gender') }}</th>
                    <th class="py-3 px-4">{{ __('Location') }}</th>
                    <th class="py-3 px-4">{{ __('Privacy / Alerts') }}</th>
                    <th class="py-3 px-4">{{ __('Joined') }}</th>
                    <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                @forelse($registeredUsers as $regUser)
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-slate-700 to-slate-800 border border-slate-600 flex items-center justify-center font-mono font-bold text-xs text-emerald-400 shrink-0">
                                    @if($regUser->avatar_path)
                                        <img src="{{ $regUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                    @else
                                        {{ strtoupper(substr($regUser->name, 0, 2)) }}
                                    @endif
                                </div>
                                <div>
                                    <div class="font-semibold text-white flex items-center gap-2">
                                        <span>{{ $regUser->name }}</span>
                                        @if($regUser->isAdmin())
                                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">ADMIN</span>
                                        @endif
                                    </div>
                                    @if($regUser->bio)
                                        <div class="text-[11px] text-slate-400 truncate max-w-[200px]" title="{{ $regUser->bio }}">
                                            {{ $regUser->bio }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-200">{{ $regUser->email }}</span>
                                <button
                                    type="button"
                                    onclick="copyText('{{ $regUser->email }}', this, '{{ __('Email copied!') }}')"
                                    title="{{ __('Copy email') }}"
                                    class="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-300 transition-colors cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                </button>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono">
                            @if($regUser->latestIp())
                                <div class="flex items-center gap-1.5">
                                    <span class="text-emerald-400 font-semibold text-[11px]">{{ $regUser->latestIp() }}</span>
                                    <button
                                        type="button"
                                        onclick="copyText('{{ $regUser->latestIp() }}', this, '{{ __('IP copied!') }}')"
                                        title="{{ __('Copy IP address') }}"
                                        class="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-300 transition-colors cursor-pointer"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    </button>
                                </div>
                            @else
                                <span class="text-slate-500 font-mono text-[11px]">—</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">
                            @if($regUser->age())
                                <span class="text-emerald-400 font-semibold">{{ $regUser->age() }} {{ __('yrs') }}</span>
                                @if($regUser->birthday)
                                    <span class="text-[10px] text-slate-500 block">({{ $regUser->birthday->format('M d, Y') }})</span>
                                @endif
                            @elseif($regUser->birthday)
                                <span class="text-slate-400">{{ $regUser->birthday->format('M d, Y') }}</span>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                            @if($regUser->hide_age || $regUser->hide_birthday)
                                <span class="text-[9px] text-amber-400 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20 font-mono inline-block mt-0.5" title="{{ __('Hidden from members') }}">
                                    🔒 {{ __('Private') }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono text-slate-300 notranslate" translate="no">
                            {{ $regUser->gender ? __($regUser->gender) : '—' }}
                        </td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">
                            @if($regUser->hasGps())
                                <div class="flex items-center gap-1.5" title="{{ __('Verified GPS: :lat, :lon', ['lat' => $regUser->latitude, 'lon' => $regUser->longitude]) }}">
                                    <span class="inline-flex items-center gap-1 text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 text-[10px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span>GPS</span>
                                    </span>
                                    <span class="text-white font-medium">{{ $regUser->city ?: $regUser->location }}</span>
                                    @if($regUser->country)
                                        <span class="text-slate-400 text-[11px]">({{ $regUser->country }})</span>
                                    @endif
                                </div>
                            @elseif($regUser->location)
                                <span>{{ $regUser->location }}</span>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                            @if($regUser->hide_location)
                                <span class="text-[9px] text-amber-400 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20 font-mono inline-block mt-0.5" title="{{ __('Location hidden from members') }}">
                                    🔒 {{ __('Private') }}
                                </span>
                            @endif
                        </td>
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
                        <td class="py-3.5 px-4 font-mono text-slate-400 text-[11px]">
                            {{ $regUser->created_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-right">
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
                                    <form method="POST" action="{{ route('admin.users.destroy', ['id' => $regUser->id]) }}" class="inline" onsubmit="return confirm('{{ __('Permanently delete this user account?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="px-2 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 transition-colors text-[11px] cursor-pointer"
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
                        <td colspan="9" class="py-8 text-center text-slate-500 font-mono">
                            {{ __('No registered users found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
