<!-- Edit Channel Modal -->
<div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4 font-sans">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Edit Channel Details') }}</h3>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
        </div>

        <form id="edit-channel-form" method="POST" action="" class="space-y-4 text-xs font-sans">
            @csrf
            @method('PUT')

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Channel Title') }}</label>
                <input
                    id="edit-input-title"
                    type="text"
                    name="title"
                    placeholder="e.g. Executive Strategy Group"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                >
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-medium text-slate-300 mb-1.5">{{ __('Channel Code') }}</label>
                    <input
                        id="edit-input-code"
                        type="text"
                        disabled
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-950/50 border border-slate-800 text-slate-500 font-mono text-sm uppercase cursor-not-allowed"
                    >
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1.5">{{ __('Channel PIN') }}</label>
                    <input
                        id="edit-input-pin"
                        type="text"
                        name="pin"
                        required
                        placeholder="PIN"
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 font-mono text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>
            </div>

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Status') }}</label>
                <select
                    id="edit-input-status"
                    name="status"
                    class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                >
                    <option value="active">{{ __('Active') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-slate-300 mb-1.5">{{ __('Enabled Translations') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach(['en' => 'English (EN)', 'ru' => 'Russian (RU)', 'fr' => 'French (FR)', 'it' => 'Italian (IT)'] as $code => $label)
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 hover:border-emerald-500/50 cursor-pointer select-none">
                            <input type="checkbox" name="allowed_languages[]" value="{{ $code }}" id="edit-lang-{{ $code }}" class="rounded bg-slate-900 border-slate-700 text-emerald-500">
                            <span class="text-xs font-mono">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 font-mono">
                <button
                    type="button"
                    onclick="closeEditModal()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer"
                >
                    {{ __('Save Changes') }}
                </button>
            </div>
        </form>
    </div>
</div>
