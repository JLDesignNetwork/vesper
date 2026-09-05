@extends('layouts.admin')

@section('content')
<div class="space-y-6" id="email-templates-root">
    <!-- Header Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-violet-950/30 border border-slate-800/80 shadow-2xl backdrop-blur-xl flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 font-mono text-xs text-violet-400 mb-1">
                <span class="w-2 h-2 rounded-full bg-violet-400 animate-pulse"></span>
                <span>{{ __('Communications Command & Dispatch Templates') }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Email Templates') }}</span>
                <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-violet-500/10 border border-violet-500/30 text-violet-400">
                    {{ count($definitions) }} {{ __('Tactical Presets') }} &bull; 4 {{ __('Locales') }}
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                {{ __('Customize automated system emails, emergency recovery dispatches, and operative notifications. Use the one-click auto-translation engine to deploy multilingual translations with zero loss of dynamic variable tokens.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 font-mono text-xs shrink-0 flex-wrap">
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-violet-400">✉️</span>
                <span class="text-slate-400">{{ __('Active Template:') }}</span>
                <strong class="text-white">{{ $activeTemplate['name'] }}</strong>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-emerald-400">🌐</span>
                <span class="text-slate-400">{{ __('Current Locale:') }}</span>
                <strong class="text-emerald-300 uppercase">{{ $activeLocale }}</strong>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

        <!-- Left Column: Template Navigator (3 cols) -->
        <div class="xl:col-span-3 space-y-4">
            <div class="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-4 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between mb-3 px-1">
                    <span class="text-xs font-mono text-slate-400 uppercase tracking-wider">{{ __('Template Library') }}</span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">{{ count($definitions) }}</span>
                </div>

                @php
                    $categories = [
                        'Authentication' => ['label' => __('Authentication & Access'), 'icon' => '🔐', 'color' => 'emerald'],
                        'Operations' => ['label' => __('Operative Comms'), 'icon' => '📡', 'color' => 'cyan'],
                        'Channels' => ['label' => __('Channel Activity'), 'icon' => '⚡', 'color' => 'amber'],
                        'Security' => ['label' => __('Security & Alerts'), 'icon' => '🛡️', 'color' => 'rose'],
                        'System' => ['label' => __('Executive & System'), 'icon' => '📊', 'color' => 'indigo'],
                    ];
                    $grouped = [];
                    foreach ($definitions as $key => $def) {
                        $cat = $def['category'] ?? 'System';
                        $grouped[$cat][$key] = $def;
                    }
                @endphp

                <div class="space-y-4">
                    @foreach($categories as $catKey => $catMeta)
                        @if(isset($grouped[$catKey]))
                            <div>
                                <div class="text-[11px] font-mono uppercase tracking-wider text-slate-500 px-2 py-1 flex items-center gap-1.5">
                                    <span>{{ $catMeta['icon'] }}</span>
                                    <span>{{ $catMeta['label'] }}</span>
                                </div>
                                <div class="space-y-1 mt-1">
                                    @foreach($grouped[$catKey] as $key => $def)
                                        @php
                                            $isActive = ($key === $activeKey);
                                            $isCustomizedCurrent = isset($customizedMatrix[$key][$activeLocale]);
                                            $customizedCount = isset($customizedMatrix[$key]) ? count($customizedMatrix[$key]) : 0;
                                        @endphp
                                        <a
                                            href="{{ route('admin.emails.index', ['template' => $key, 'locale' => $activeLocale]) }}"
                                            class="w-full text-left px-3 py-2.5 rounded-xl border flex flex-col gap-1 transition-all group {{ $isActive ? 'bg-violet-500/15 border-violet-500/40 text-violet-200 shadow-md shadow-violet-950/40' : 'bg-slate-950/40 border-slate-800/60 text-slate-300 hover:bg-slate-800/60 hover:text-white hover:border-slate-700' }}"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-medium {{ $isActive ? 'text-violet-200 font-semibold' : 'group-hover:text-white' }} truncate">
                                                    {{ $def['name'] }}
                                                </span>
                                                @if($isCustomizedCurrent)
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" title="{{ __('Customized in current locale') }}"></span>
                                                @endif
                                            </div>
                                            <div class="flex items-center justify-between text-[10px] font-mono text-slate-500">
                                                <span class="truncate max-w-[140px]">{{ $key }}</span>
                                                <span>
                                                    @if($customizedCount > 0)
                                                        <span class="text-emerald-400">{{ $customizedCount }}/4 {{ __('custom') }}</span>
                                                    @else
                                                        <span class="text-slate-600">{{ __('factory') }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Center & Right: Editor Form & Live Preview (9 cols) -->
        <div class="xl:col-span-9 space-y-6">

            <!-- Locale Selection & Auto-Translate Action Bar -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 shadow-xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Locale Tabs -->
                <div class="flex items-center gap-1.5 p-1 bg-slate-950/60 border border-slate-800/80 rounded-xl font-mono text-xs">
                    @foreach(['en' => 'English', 'it' => 'Italiano', 'fr' => 'Français', 'ru' => 'Русский'] as $loc => $label)
                        @php
                            $isTabActive = ($activeLocale === $loc);
                            $isCustomized = isset($customizedMatrix[$activeKey][$loc]);
                        @endphp
                        <a
                            href="{{ route('admin.emails.index', ['template' => $activeKey, 'locale' => $loc]) }}"
                            class="px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all {{ $isTabActive ? 'bg-violet-600 text-white font-semibold shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/70' }}"
                        >
                            <span class="uppercase font-bold">{{ $loc }}</span>
                            <span class="hidden sm:inline text-[11px] opacity-80">{{ $label }}</span>
                            @if($isCustomized)
                                <span class="w-1.5 h-1.5 rounded-full {{ $isTabActive ? 'bg-white' : 'bg-emerald-400' }}"></span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <!-- One-Click Auto-Translation Trigger -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="btn-auto-translate"
                        onclick="triggerAutoTranslate('{{ $activeKey }}')"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-medium shadow-lg shadow-violet-950/50 hover:shadow-violet-900/50 transition-all flex items-center justify-center gap-2 cursor-pointer border border-violet-400/30"
                        title="{{ __('Translate current English copy to Italian, French, and Russian automatically') }}"
                    >
                        <svg id="translate-icon-svg" class="w-4 h-4 text-violet-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span id="translate-spinner" class="hidden w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span id="translate-btn-text">{{ __('Auto-Translate to IT, FR, RU') }}</span>
                    </button>
                </div>
            </div>

            <!-- Editor & Live Preview Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Editor Form (7 cols on lg) -->
                <div class="lg:col-span-7 bg-slate-900/80 border border-slate-800/80 rounded-2xl p-5 shadow-xl backdrop-blur-xl space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div>
                            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                                <span>{{ $activeTemplate['name'] }}</span>
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-400 uppercase">{{ $activeLocale }}</span>
                            </h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $activeTemplate['description'] }}</p>
                        </div>
                        <div>
                            @if(isset($customizedMatrix[$activeKey][$activeLocale]))
                                <span class="text-[10px] font-mono px-2 py-1 rounded-md bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    {{ __('Customized') }}
                                </span>
                            @else
                                <span class="text-[10px] font-mono px-2 py-1 rounded-md bg-slate-800/60 border border-slate-700 text-slate-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                    {{ __('Factory Preset') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Available Variable Tokens Tray -->
                    <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-mono text-slate-400">
                            <span class="flex items-center gap-1.5">
                                <span class="text-violet-400">🏷️</span>
                                <span>{{ __('Dynamic Variable Placeholders (Click to insert):') }}</span>
                            </span>
                            <span class="text-[10px] text-slate-500">{{ __('Preserved during auto-translation') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($activeTemplate['variables'] as $varToken => $varDesc)
                                <button
                                    type="button"
                                    onclick="insertVariableToken('{{ $varToken }}')"
                                    class="px-2 py-1 rounded-lg bg-slate-900 hover:bg-violet-950/50 border border-slate-800 hover:border-violet-500/40 text-violet-300 hover:text-violet-200 font-mono text-[11px] transition-all cursor-pointer flex items-center gap-1 group"
                                    title="{{ $varDesc }}"
                                >
                                    <span>{{ $varToken }}</span>
                                    <span class="text-[9px] text-slate-500 group-hover:text-violet-400">+</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Editor Form -->
                    <form id="template-edit-form" method="POST" action="{{ route('admin.emails.update', $activeKey) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="locale" id="form-locale-input" value="{{ $activeLocale }}">

                        <!-- Subject Line -->
                        <div class="space-y-1.5">
                            <label for="input-subject" class="block text-xs font-mono text-slate-300">
                                {{ __('Subject Line') }} <span class="text-rose-400">*</span>
                            </label>
                            <input
                                type="text"
                                name="subject"
                                id="input-subject"
                                value="{{ old('subject', $activeTemplate['subject']) }}"
                                required
                                oninput="handleEditorInput()"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-100 text-xs font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500/50 transition-all outline-none"
                                placeholder="{{ __('Enter email subject line with placeholders...') }}"
                            >
                        </div>

                        <!-- Preheader Snippet -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="input-preheader" class="block text-xs font-mono text-slate-300">
                                    {{ __('Preheader Snippet') }}
                                </label>
                                <span class="text-[10px] font-mono text-slate-500">{{ __('Inbox preview snippet') }}</span>
                            </div>
                            <input
                                type="text"
                                name="preheader"
                                id="input-preheader"
                                value="{{ old('preheader', $activeTemplate['preheader']) }}"
                                oninput="handleEditorInput()"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-100 text-xs font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500/50 transition-all outline-none"
                                placeholder="{{ __('Optional summary preview text displayed in email clients...') }}"
                            >
                        </div>

                        <!-- Markdown Body -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="input-body-markdown" class="block text-xs font-mono text-slate-300">
                                    {{ __('Body Content (Markdown & HTML Supported)') }} <span class="text-rose-400">*</span>
                                </label>
                                <!-- Quick Markdown Formatting Helpers -->
                                <div class="flex items-center gap-1 font-mono text-[11px]">
                                    <button type="button" onclick="formatMarkdown('**', '**')" class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white" title="{{ __('Bold') }}">B</button>
                                    <button type="button" onclick="formatMarkdown('*', '*')" class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white italic" title="{{ __('Italic') }}">I</button>
                                    <button type="button" onclick="formatMarkdown('`', '`')" class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white" title="{{ __('Code') }}">&lt;&gt;</button>
                                    <button type="button" onclick="formatMarkdown('> ', '')" class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white" title="{{ __('Quote / Callout') }}">&ldquo;</button>
                                    <button type="button" onclick="formatMarkdown('- ', '')" class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white" title="{{ __('Bullet List') }}">&bull;</button>
                                </div>
                            </div>
                            <textarea
                                name="body_markdown"
                                id="input-body-markdown"
                                rows="10"
                                required
                                oninput="handleEditorInput()"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-100 text-xs font-mono leading-relaxed focus:border-violet-500 focus:ring-1 focus:ring-violet-500/50 transition-all outline-none resize-y"
                                placeholder="{{ __('Write email message in Markdown format...') }}"
                            >{{ old('body_markdown', $activeTemplate['body_markdown']) }}</textarea>
                        </div>

                        <!-- Action Button Settings -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3.5 rounded-xl bg-slate-950/50 border border-slate-800/80">
                            <div class="space-y-1.5">
                                <label for="input-button-text" class="block text-xs font-mono text-slate-300">
                                    {{ __('Primary CTA Button Label') }}
                                </label>
                                <input
                                    type="text"
                                    name="button_text"
                                    id="input-button-text"
                                    value="{{ old('button_text', $activeTemplate['button_text']) }}"
                                    oninput="handleEditorInput()"
                                    class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-100 text-xs font-mono focus:border-violet-500 outline-none"
                                    placeholder="{{ __('e.g., Access Terminal (Leave empty to hide)') }}"
                                >
                            </div>

                            <div class="space-y-1.5">
                                <label for="input-button-color" class="block text-xs font-mono text-slate-300">
                                    {{ __('Button Accent Styling') }}
                                </label>
                                <select
                                    name="button_color"
                                    id="input-button-color"
                                    onchange="handleEditorInput()"
                                    class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-100 text-xs font-mono focus:border-violet-500 outline-none"
                                >
                                    <option value="success" {{ old('button_color', $activeTemplate['button_color']) === 'success' ? 'selected' : '' }}>🟢 {{ __('Emerald (Operations / Success)') }}</option>
                                    <option value="cyan" {{ old('button_color', $activeTemplate['button_color']) === 'cyan' ? 'selected' : '' }}>🔵 {{ __('Cyan (Channels / Discovery)') }}</option>
                                    <option value="error" {{ old('button_color', $activeTemplate['button_color']) === 'error' ? 'selected' : '' }}>🔴 {{ __('Rose (Emergency / Security Alert)') }}</option>
                                    <option value="amber" {{ old('button_color', $activeTemplate['button_color']) === 'amber' ? 'selected' : '' }}>🟡 {{ __('Amber (Warning / Channel Burn)') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Footer Legal / Disclaimer -->
                        <div class="space-y-1.5">
                            <label for="input-footer-text" class="block text-xs font-mono text-slate-300">
                                {{ __('Footer Security Disclaimer & Signature') }}
                            </label>
                            <textarea
                                name="footer_text"
                                id="input-footer-text"
                                rows="3"
                                oninput="handleEditorInput()"
                                class="w-full px-3.5 py-2 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-300 text-xs font-mono focus:border-violet-500 outline-none resize-y"
                                placeholder="{{ __('Tactical compliance disclaimer...') }}"
                            >{{ old('footer_text', $activeTemplate['footer_text']) }}</textarea>
                        </div>

                        <!-- Action Button Bar -->
                        <div class="pt-3 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="submit"
                                    id="btn-save-template"
                                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-semibold shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/50 transition-all flex items-center gap-2 cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>{{ __('Save Custom Template') }}</span>
                                </button>

                                <button
                                    type="button"
                                    onclick="sendTestDispatch('{{ $activeKey }}')"
                                    id="btn-send-test"
                                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-mono transition-all flex items-center gap-2 cursor-pointer border border-slate-700"
                                    title="{{ __('Dispatch live test render to your administrator email') }}"
                                >
                                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>{{ __('Send Test Email') }}</span>
                                </button>
                            </div>

                            @if(isset($customizedMatrix[$activeKey][$activeLocale]))
                                <button
                                    type="button"
                                    onclick="resetToFactoryDefault('{{ $activeKey }}')"
                                    class="px-3.5 py-2 rounded-xl bg-rose-950/30 hover:bg-rose-900/40 text-rose-400 border border-rose-900/50 text-xs font-mono transition-all flex items-center gap-1.5 cursor-pointer"
                                    title="{{ __('Discard custom changes and restore factory defaults') }}"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    <span>{{ __('Reset to Default') }}</span>
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Live Preview Pane (5 cols on lg) -->
                <div class="lg:col-span-5 space-y-3 sticky top-20">
                    <div class="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-4 shadow-xl backdrop-blur-xl space-y-3">
                        <!-- Preview Controls -->
                        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-slate-300 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>{{ __('Live Preview') }}</span>
                                </span>
                                <span id="preview-sync-status" class="text-[10px] font-mono text-slate-500">{{ __('Synced') }}</span>
                            </div>

                            <!-- Viewport Width Switcher -->
                            <div class="flex items-center gap-1 p-0.5 bg-slate-950 rounded-lg border border-slate-800 text-[11px] font-mono">
                                <button
                                    type="button"
                                    onclick="setPreviewViewport('desktop')"
                                    id="btn-viewport-desktop"
                                    class="px-2 py-1 rounded bg-slate-800 text-white font-medium transition-all"
                                    title="{{ __('Desktop 600px') }}"
                                >
                                    🖥️ {{ __('Desktop') }}
                                </button>
                                <button
                                    type="button"
                                    onclick="setPreviewViewport('mobile')"
                                    id="btn-viewport-mobile"
                                    class="px-2 py-1 rounded text-slate-400 hover:text-white transition-all"
                                    title="{{ __('Mobile 360px') }}"
                                >
                                    📱 {{ __('Mobile') }}
                                </button>
                            </div>
                        </div>

                        <!-- Live Subject Preview Bar -->
                        <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/60 font-mono text-xs space-y-1">
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider">{{ __('Subject Preview:') }}</div>
                            <div id="preview-subject-text" class="text-slate-200 font-medium text-xs truncate">
                                {{ $activeTemplate['subject'] }}
                            </div>
                        </div>

                        <!-- Preview Iframe Container -->
                        <div id="preview-iframe-wrapper" class="w-full flex justify-center bg-slate-950 rounded-xl border border-slate-800/80 overflow-hidden shadow-inner min-h-[620px] transition-all">
                            <iframe
                                id="email-preview-iframe"
                                src="{{ route('admin.emails.preview', ['key' => $activeKey, 'locale' => $activeLocale]) }}"
                                class="w-full h-[620px] border-0 transition-all duration-300"
                                title="{{ __('Email Preview') }}"
                            ></iframe>
                        </div>

                        <div class="flex items-center justify-between text-[11px] font-mono text-slate-500 pt-1">
                            <div class="flex items-center gap-1">
                                <span>🔒</span>
                                <span>{{ __('Sample test variables applied') }}</span>
                            </div>
                            <a
                                href="{{ route('admin.emails.preview', ['key' => $activeKey, 'locale' => $activeLocale]) }}"
                                target="_blank"
                                class="text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                            >
                                <span>{{ __('Open Tab') }}</span>
                                <span>&nearr;</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Reset Confirmation Form -->
<form id="reset-template-form" method="POST" action="{{ route('admin.emails.reset', $activeKey) }}" class="hidden">
    @csrf
    <input type="hidden" name="locale" value="{{ $activeLocale }}">
</form>

<script>
    let lastActiveField = document.getElementById('input-body-markdown');
    let previewDebounceTimer = null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const previewUrl = "{{ route('admin.emails.preview', $activeKey) }}";
    const autoTranslateUrl = "{{ route('admin.emails.auto-translate', $activeKey) }}";
    const testSendUrl = "{{ route('admin.emails.test', $activeKey) }}";

    // Track active cursor in subject or body
    ['input-subject', 'input-preheader', 'input-body-markdown', 'input-button-text', 'input-footer-text'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('focus', () => { lastActiveField = el; });
        }
    });

    function insertVariableToken(token) {
        if (!lastActiveField) lastActiveField = document.getElementById('input-body-markdown');
        const start = lastActiveField.selectionStart ?? lastActiveField.value.length;
        const end = lastActiveField.selectionEnd ?? lastActiveField.value.length;
        const text = lastActiveField.value;
        lastActiveField.value = text.substring(0, start) + token + text.substring(end);
        lastActiveField.focus();
        lastActiveField.setSelectionRange(start + token.length, start + token.length);
        handleEditorInput();
    }

    function formatMarkdown(prefix, suffix) {
        const field = document.getElementById('input-body-markdown');
        if (!field) return;
        const start = field.selectionStart ?? 0;
        const end = field.selectionEnd ?? 0;
        const text = field.value;
        const selected = text.substring(start, end);
        field.value = text.substring(0, start) + prefix + selected + suffix + text.substring(end);
        field.focus();
        const cursor = selected.length ? end + prefix.length + suffix.length : start + prefix.length;
        field.setSelectionRange(cursor, cursor);
        handleEditorInput();
    }

    function handleEditorInput() {
        const subject = document.getElementById('input-subject').value;
        document.getElementById('preview-subject-text').innerText = subject;
        document.getElementById('preview-sync-status').innerText = '{{ __("Drafting...") }}';
        document.getElementById('preview-sync-status').className = 'text-[10px] font-mono text-amber-400';

        clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(refreshPreviewIframe, 400);
    }

    async function refreshPreviewIframe() {
        const iframe = document.getElementById('email-preview-iframe');
        if (!iframe) return;

        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('locale', document.getElementById('form-locale-input').value);
        formData.append('subject', document.getElementById('input-subject').value);
        formData.append('preheader', document.getElementById('input-preheader').value);
        formData.append('body_markdown', document.getElementById('input-body-markdown').value);
        formData.append('button_text', document.getElementById('input-button-text').value);
        formData.append('button_color', document.getElementById('input-button-color').value);
        formData.append('footer_text', document.getElementById('input-footer-text').value);

        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'text/html'
                }
            });

            if (res.ok) {
                const html = await res.text();
                iframe.srcdoc = html;
                document.getElementById('preview-sync-status').innerText = '{{ __("Synced") }}';
                document.getElementById('preview-sync-status').className = 'text-[10px] font-mono text-emerald-400';
            }
        } catch (err) {
            console.error('Preview refresh failed:', err);
            document.getElementById('preview-sync-status').innerText = '{{ __("Sync error") }}';
            document.getElementById('preview-sync-status').className = 'text-[10px] font-mono text-rose-400';
        }
    }

    function setPreviewViewport(mode) {
        const wrapper = document.getElementById('preview-iframe-wrapper');
        const iframe = document.getElementById('email-preview-iframe');
        const btnDesktop = document.getElementById('btn-viewport-desktop');
        const btnMobile = document.getElementById('btn-viewport-mobile');

        if (mode === 'mobile') {
            iframe.style.width = '375px';
            btnMobile.className = 'px-2 py-1 rounded bg-slate-800 text-white font-medium transition-all';
            btnDesktop.className = 'px-2 py-1 rounded text-slate-400 hover:text-white transition-all';
        } else {
            iframe.style.width = '100%';
            btnDesktop.className = 'px-2 py-1 rounded bg-slate-800 text-white font-medium transition-all';
            btnMobile.className = 'px-2 py-1 rounded text-slate-400 hover:text-white transition-all';
        }
    }

    async function triggerAutoTranslate(key) {
        const btn = document.getElementById('btn-auto-translate');
        const spinner = document.getElementById('translate-spinner');
        const icon = document.getElementById('translate-icon-svg');
        const text = document.getElementById('translate-btn-text');

        if (!confirm('{{ __("Auto-translate this template from English into Italian, French, and Russian? Dynamic variables will be automatically protected.") }}')) {
            return;
        }

        btn.disabled = true;
        spinner.classList.remove('hidden');
        icon.classList.add('hidden');
        text.innerText = '{{ __("Translating Preserving Variables...") }}';

        try {
            const res = await fetch(autoTranslateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    from_locale: 'en',
                    target_locales: ['it', 'fr', 'ru']
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message || '{{ __("Auto-translation complete!") }}');
                window.location.reload();
            } else {
                alert(data.message || '{{ __("Translation encountered an error.") }}');
            }
        } catch (err) {
            console.error('Translation error:', err);
            alert('{{ __("Translation service request failed.") }}');
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
            icon.classList.remove('hidden');
            text.innerText = '{{ __("Auto-Translate to IT, FR, RU") }}';
        }
    }

    async function sendTestDispatch(key) {
        const btn = document.getElementById('btn-send-test');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `
            <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ __("Dispatching...") }}</span>
        `;

        try {
            const res = await fetch(testSendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    locale: document.getElementById('form-locale-input').value
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message || '{{ __("Test email dispatched successfully.") }}');
            } else {
                alert(data.message || '{{ __("Failed to dispatch test email.") }}');
            }
        } catch (err) {
            console.error('Test dispatch error:', err);
            alert('{{ __("Test dispatch network error.") }}');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function resetToFactoryDefault(key) {
        if (confirm('{{ __("Reset this template in the current language back to factory defaults? All custom text will be removed.") }}')) {
            document.getElementById('reset-template-form').submit();
        }
    }
</script>
@endsection
