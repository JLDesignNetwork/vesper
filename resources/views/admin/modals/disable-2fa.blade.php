    <!-- Two-Factor Disable Confirmation Modal -->
    <div id="two-factor-disable-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto p-3 sm:p-4 flex items-center justify-center font-sans">
        <div class="w-full max-w-sm my-auto bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-semibold text-rose-400 flex items-center gap-2">
                    <span>⚠️</span>
                    <span>{{ __('Disable Two-Factor Authentication') }}</span>
                </h3>
                <button type="button" onclick="closeDisable2faModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>
            <p class="text-xs text-slate-400">
                {{ __('Confirm your password to deactivate two-factor authentication and purge all associated security backup codes.') }}
            </p>
            <div id="disable-2fa-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>
            <div>
                <input
                    type="password"
                    id="disable-2fa-password"
                    placeholder="{{ __('Current Password') }}"
                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-rose-500"
                >
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button
                    type="button"
                    onclick="closeDisable2faModal()"
                    class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    onclick="submitDisable2fa()"
                    id="confirm-disable-2fa-btn"
                    class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-medium cursor-pointer"
                >
                    {{ __('Confirm Deactivation') }}
                </button>
            </div>
        </div>
    </div>
