<!-- User Dossier Modal -->
<div id="user-dossier-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto p-3 sm:p-4 flex items-center justify-center">
    <div class="w-full max-w-lg my-auto bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden font-sans">
        <div class="flex items-center justify-between p-4 sm:p-5 pb-3 sm:pb-4 border-b border-slate-800 shrink-0 bg-slate-900">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center font-mono font-bold text-sm text-emerald-400 shrink-0">
                    <img id="dossier-avatar-img" src="" class="w-full h-full object-cover hidden" alt="">
                    <span id="dossier-avatar"></span>
                </div>
                <div>
                    <h3 id="dossier-name" class="text-base font-semibold text-white tracking-tight"></h3>
                </div>
            </div>
            <button type="button" onclick="closeUserDossier()" class="text-slate-400 hover:text-white cursor-pointer text-sm">✕</button>
        </div>

        <!-- Scrollable Body -->
        <div class="overflow-y-auto p-4 sm:p-5 space-y-4 flex-1 modal-scroll">
            <div class="grid grid-cols-2 gap-3 text-xs font-mono">
                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Email Address') }}</div>
                    <div id="dossier-email" class="text-slate-200 mt-1 font-semibold truncate"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Latest IP Address') }}</div>
                    <div class="flex items-center justify-between mt-1">
                        <span id="dossier-ip-val" class="text-emerald-400 font-semibold truncate">—</span>
                        <button
                            type="button"
                            id="dossier-copy-ip-btn"
                            onclick="copyDossierIp()"
                            title="{{ __('Copy IP') }}"
                            class="text-slate-400 hover:text-emerald-300 transition-colors p-0.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        </button>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Age / Birthday') }}</div>
                    <div id="dossier-age" class="text-slate-200 mt-1 font-semibold"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Gender') }}</div>
                    <div id="dossier-gender" class="text-slate-200 mt-1 notranslate" translate="no"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Location') }}</div>
                    <div id="dossier-location" class="text-slate-200 mt-1"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Email Alerts') }}</div>
                    <div id="dossier-alerts" class="text-slate-200 mt-1 font-semibold"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Language Preference') }}</div>
                    <div id="dossier-language" class="text-slate-200 mt-1 font-semibold flex items-center gap-1.5"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80 col-span-2">
                    <div class="text-[10px] text-slate-500 uppercase mb-1">{{ __('Member Privacy Controls (What regular members can see)') }}</div>
                    <div id="dossier-privacy-summary" class="text-slate-300 font-mono text-[11px]"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80 col-span-2">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Registered Date') }}</div>
                    <div id="dossier-joined" class="text-slate-200 mt-1"></div>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80">
                <div class="flex items-center justify-between mb-1.5 font-mono">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Bio / Notes') }}</div>
                    <span id="dossier-bio-badge" class="hidden text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20">🔒 {{ __('Hidden from members') }}</span>
                </div>
                <p id="dossier-bio" class="text-xs text-slate-300 leading-relaxed italic whitespace-pre-wrap"></p>
            </div>
        </div>

        <!-- Pinned Footer -->
        <div class="p-3.5 sm:p-4 border-t border-slate-800 bg-slate-950/70 flex items-center justify-end gap-2 font-mono text-xs shrink-0">
            <button
                type="button"
                onclick="closeUserDossier()"
                class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
            >
                {{ __('Close') }}
            </button>
            <button
                type="button"
                id="dossier-edit-my-profile-btn"
                onclick="closeUserDossier(); openProfileModal();"
                class="hidden px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium transition-colors cursor-pointer"
            >
                {{ __('Edit My Profile') }}
            </button>
        </div>
    </div>
</div>
