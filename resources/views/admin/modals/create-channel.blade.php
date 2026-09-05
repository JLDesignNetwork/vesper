<!-- Create Channel Modal -->
<div id="create-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4 font-sans">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Create Private Channel') }}</h3>
            <button type="button" onclick="closeCreateModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.channels.store') }}" class="space-y-4 text-xs font-sans">
            @csrf

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Channel Title') }}</label>
                <input
                    type="text"
                    name="title"
                    placeholder="e.g. Executive Strategy Group"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                >
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="font-medium text-slate-300">{{ __('Channel Code') }}</label>
                        <button type="button" onclick="generateChannelCode()" class="text-[10px] text-emerald-400 hover:underline cursor-pointer font-mono">Auto</button>
                    </div>
                    <input
                        id="input-code"
                        type="text"
                        name="code"
                        placeholder="ALPHA-01"
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 font-mono text-sm focus:outline-none focus:border-emerald-500 uppercase"
                    >
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="font-medium text-slate-300">{{ __('Access Passcode') }}</label>
                        <button type="button" onclick="generatePasscode()" class="text-[10px] text-emerald-400 hover:underline cursor-pointer font-mono">PIN</button>
                    </div>
                    <input
                        id="input-passcode"
                        type="text"
                        name="passcode"
                        required
                        placeholder="Passcode"
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 font-mono text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>
            </div>

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Enabled Translations') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach(['en' => 'English (EN)', 'ru' => 'Russian (RU)', 'fr' => 'French (FR)', 'it' => 'Italian (IT)'] as $code => $label)
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 hover:border-emerald-500/50 cursor-pointer select-none">
                            <input type="checkbox" name="allowed_languages[]" value="{{ $code }}" checked class="rounded bg-slate-900 border-slate-700 text-emerald-500">
                            <span class="text-xs font-mono">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-slate-500 mt-1">{{ __('Select which translations can be used inside this specific channel.') }}</p>
            </div>

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Lifespan') }}</label>
                <select
                    name="expiration"
                    class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                >
                    <option value="24h" selected>{{ __('24 Hours (Recommended)') }}</option>
                    <option value="1h">{{ __('1 Hour') }}</option>
                    <option value="7d">{{ __('7 Days') }}</option>
                    <option value="permanent">{{ __('Permanent (No Expiration)') }}</option>
                </select>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-slate-300 select-none">
                    <input type="checkbox" name="burn_after_reading" value="1" class="rounded bg-slate-950 border-slate-800 text-emerald-500">
                    <span>{{ __('Erase messages once viewed by recipient') }}</span>
                </label>
            </div>

            <div class="pt-1">
                <label class="flex items-center gap-2 cursor-pointer text-slate-300 select-none">
                    <input type="checkbox" name="notify_admin" value="1" class="rounded bg-slate-950 border-slate-800 text-emerald-500">
                    <span>{{ __('Notify Admin on New Messages') }}</span>
                </label>
                <p class="text-[11px] text-slate-500 ml-6">{{ __('Receive an email alert whenever a message is posted in this channel.') }}</p>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 font-mono">
                <button
                    type="button"
                    onclick="closeCreateModal()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer"
                >
                    {{ __('Create Channel') }}
                </button>
            </div>
        </form>
    </div>
</div>
