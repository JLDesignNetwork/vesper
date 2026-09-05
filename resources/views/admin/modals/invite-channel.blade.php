<!-- Invite Operative / Generate Channel Invitation Modal -->
<div id="invite-channel-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4 font-sans">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div>
                <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Channel Invitation Protocol') }}</h3>
                <p class="text-xs text-slate-400 font-mono mt-0.5" id="invite-channel-subtitle"></p>
            </div>
            <button type="button" onclick="closeInviteModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
        </div>

        <form id="invite-channel-form" method="POST" action="" class="space-y-4 text-xs">
            @csrf

            <!-- Option A: Direct Assignment to Registered Operative -->
            <div class="p-3.5 bg-slate-950/70 border border-slate-800 rounded-xl space-y-2">
                <label class="block font-semibold text-emerald-400 font-mono text-[11px] uppercase tracking-wider">{{ __('Direct Assignment') }}</label>
                <p class="text-[11px] text-slate-400">{{ __('Instantly grant clearance to an existing registered operative.') }}</p>
                <select name="user_id" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500">
                    <option value="">{{ __('-- Select Registered Operative --') }}</option>
                    @foreach($registeredUsers ?? [] as $regUser)
                        @if(!$regUser->isAdmin())
                            <option value="{{ $regUser->id }}">{{ $regUser->name }} ({{ $regUser->email }})</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div class="text-center text-[10px] font-mono text-slate-500 uppercase tracking-widest">{{ __('— OR Generate Invite Token / Code —') }}</div>

            <!-- Option B: Clearance Code & Link -->
            <div class="p-3.5 bg-slate-950/70 border border-slate-800 rounded-xl space-y-3">
                <label class="block font-semibold text-cyan-400 font-mono text-[11px] uppercase tracking-wider">{{ __('Shareable Clearance Token') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 text-[11px] mb-1">{{ __('Max Redemptions') }}</label>
                        <input type="number" name="max_uses" min="1" max="100" placeholder="Unlimited" class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[11px] mb-1">{{ __('Expires After') }}</label>
                        <select name="expires_in_days" class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-cyan-500">
                            <option value="7">{{ __('7 Days') }}</option>
                            <option value="1">{{ __('24 Hours') }}</option>
                            <option value="30">{{ __('30 Days') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 font-mono">
                <button
                    type="button"
                    onclick="closeInviteModal()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-teal-600 hover:from-cyan-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer"
                >
                    {{ __('Issue Invitation') }}
                </button>
            </div>
        </form>
    </div>
</div>
