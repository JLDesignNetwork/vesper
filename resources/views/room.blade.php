<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $room->title ?: $room->code }} // {{ __('Vesper Private Communications') }}</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Leaflet CSS for Intel Radar Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Custom scrollbar for chat stream */
        .chat-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .chat-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 9999px;
        }
        .chat-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.35);
        }
        /* Custom scrollbar for modal dialogs */
        .modal-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .modal-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .modal-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 9999px;
        }
        .modal-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.4);
        }
        /* Leaflet custom dark map style */
        .leaflet-container {
            background: #090d16 !important;
            font-family: inherit;
        }
        .leaflet-popup-content-wrapper, .leaflet-popup-tip {
            background: #0f172a !important;
            color: #f1f5f9 !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
            font-size: 11px !important;
            font-family: 'JetBrains Mono', monospace !important;
        }
    </style>
</head>
<body class="bg-[#07090e] text-slate-100 h-[100dvh] flex flex-col font-sans antialiased overflow-hidden select-none">

    <!-- Top Navigation / Channel Control Bar -->
    <header class="h-16 border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-md px-3 sm:px-6 flex items-center justify-between shrink-0 z-20">
        <!-- Channel Info -->
        <div class="flex items-center gap-3 min-w-0">
            <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-400 font-mono font-bold text-sm shadow-[0_0_15px_rgba(16,185,129,0.2)] shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                </svg>
                <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
            </div>

            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="font-mono text-sm sm:text-base font-bold text-white tracking-wider truncate">
                        {{ $room->code }}
                    </h1>
                    <button
                        type="button"
                        onclick="copyChannelLink()"
                        id="copy-link-btn"
                        class="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-400 transition-colors"
                        title="{{ __('Copy Channel Link') }}"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                    @if($room->burn_after_reading)
                        <span class="hidden sm:inline-flex px-1.5 py-0.5 rounded bg-rose-500/10 border border-rose-500/30 text-[10px] font-mono text-rose-300">
                            {{ __('BURN-READ') }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2 text-[11px] text-slate-400 font-mono">
                    <span class="truncate text-slate-300">{{ $room->title ?: __('Classified Transmissions') }}</span>
                    <span>•</span>
                    <span class="text-emerald-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span id="active-count-badge">1 {{ __('Active') }}</span>
                    </span>
                    @if($room->expires_at)
                        <span class="hidden md:inline text-slate-500">•</span>
                        <span class="hidden md:inline text-amber-400/90" title="{{ $room->expires_at }}">
                            {{ __('Auto-Destruct') }}: {{ $room->expires_at->diffForHumans(['parts' => 1]) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center gap-2 font-mono">
            <!-- Language Switcher (Scoped to Channel's Assigned Languages) -->
            @php
                $supportedMap = ['en' => 'EN', 'ru' => 'RU', 'fr' => 'FR', 'it' => 'IT'];
                $channelAllowedLangs = $room->effectiveAllowedLanguages();
            @endphp
            <div class="flex items-center gap-1 bg-slate-900 border border-slate-800 p-1 rounded-lg text-xs">
                @foreach($supportedMap as $code => $label)
                    @if(in_array($code, $channelAllowedLangs, true))
                        <a
                            href="{{ route('locale.switch', ['locale' => $code]) }}"
                            class="px-2 py-0.5 rounded transition-all {{ app()->getLocale() === $code ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/40' : 'text-slate-400 hover:text-white' }}"
                        >
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </div>

            <!-- Profile Settings Button (if authenticated) -->
            @if(Auth::check())
                <button
                    type="button"
                    onclick="openProfileModal()"
                    class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs flex items-center gap-2 transition-colors cursor-pointer"
                    title="{{ __('Edit Profile Details') }}"
                >
                    <div class="w-5 h-5 rounded-full overflow-hidden bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold text-[10px] shrink-0">
                        @if(Auth::user()->avatar_path)
                            <img id="header-avatar-img" src="{{ Auth::user()->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                            <span id="header-avatar-initial" class="hidden">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                        @else
                            <img id="header-avatar-img" src="" class="w-full h-full object-cover hidden" alt="">
                            <span id="header-avatar-initial">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <span id="header-user-name" class="hidden sm:inline max-w-[90px] truncate">{{ Auth::user()->name }}</span>
                </button>
            @endif

            <!-- Audio chime toggle -->
            <button
                type="button"
                id="sound-toggle-btn"
                onclick="toggleSound()"
                class="p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-emerald-400 transition-colors"
                title="{{ __('Toggle audio chimes') }}"
            >
                <svg id="sound-icon-on" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                </svg>
                <svg id="sound-icon-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
            </button>

            <!-- Intel Radar Drawer Toggle -->
            <button
                type="button"
                onclick="toggleRadar()"
                class="px-2.5 sm:px-3 py-1.5 rounded-lg bg-cyan-950/40 border border-cyan-500/30 hover:bg-cyan-900/40 text-cyan-300 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-[0_0_12px_rgba(6,182,212,0.15)] cursor-pointer"
                title="{{ __('INTEL RADAR') }}"
            >
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span class="hidden sm:inline">{{ __('INTEL RADAR') }}</span>
                <span class="sm:hidden">{{ __('RADAR') }}</span>
            </button>

            <!-- Lock Session -->
            <form action="{{ route('rooms.lock', ['room' => $room->code]) }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-amber-400 transition-colors cursor-pointer"
                    title="{{ __('Lock Session (Leave Room)') }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </button>
            </form>

            <!-- Emergency Nuke / Self-Destruct -->
            <button
                type="button"
                onclick="openNukeModal()"
                class="px-2.5 sm:px-3 py-1.5 rounded-lg bg-rose-950/50 border border-rose-500/40 hover:bg-rose-900/50 text-rose-300 text-xs font-bold flex items-center gap-1 transition-all shadow-[0_0_12px_rgba(244,63,94,0.15)] cursor-pointer"
                title="{{ __('NUKE') }}"
            >
                <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span class="hidden sm:inline">{{ __('NUKE') }}</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace: Chat Stream + Overlay/Drawers -->
    <div class="flex-1 flex overflow-hidden relative" id="chat-container">

        <!-- Drag and Drop Overlay -->
        <div id="drop-overlay" class="absolute inset-0 bg-emerald-950/85 backdrop-blur-md z-40 border-4 border-dashed border-emerald-400 flex flex-col items-center justify-center pointer-events-none opacity-0 transition-opacity duration-200">
            <svg class="w-16 h-16 text-emerald-400 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p class="font-mono font-bold text-lg text-emerald-300 mt-4 tracking-wider">{{ __('TRANSMIT ATTACHMENT') }}</p>
            <p class="font-mono text-xs text-emerald-400/80 mt-1">{{ __('Drop image, video, or file to encrypt & upload') }}</p>
        </div>

        <!-- Chat Stream Panel -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#06080d] relative">

            <!-- Message Stream Area -->
            <div id="message-stream" class="flex-1 overflow-y-auto chat-scroll p-4 sm:p-6 space-y-4 select-text">
                <!-- Welcome Banner -->
                <div class="max-w-md mx-auto text-center py-6 px-4 rounded-xl bg-slate-900/40 border border-slate-800/80 font-mono text-xs text-slate-400 space-y-2">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-400 mb-1">
                        🔒
                    </div>
                    <p class="text-white font-semibold tracking-wider uppercase">{{ __('SECURE GHOSTWIRE STREAM INITIALIZED') }}</p>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        {{ __('End-to-end verified communication via Ghostwire Protocol. Transmit text messages, encrypted photos, and streaming video.') }}
                    </p>
                    <div class="pt-2 flex items-center justify-center gap-3 text-[10px] text-slate-500">
                        <span>{{ __('Your Alias') }}: <strong class="text-emerald-400">{{ $alias }}</strong></span>
                        <span>•</span>
                        <span>{{ __('IP Node') }}: <strong class="text-slate-300">{{ $clientIp }}</strong></span>
                    </div>
                </div>

                <!-- Messages will be dynamically rendered here -->
                <div id="messages-list" class="space-y-4"></div>
            </div>

            <!-- Pre-send Attachment Chip (if file selected) -->
            <div id="attachment-preview-bar" class="hidden px-4 py-2 bg-slate-900/90 border-t border-slate-800 flex items-center justify-between font-mono text-xs text-slate-300">
                <div class="flex items-center gap-3 min-w-0">
                    <div id="attachment-thumb-container" class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                        <!-- Preview img or icon injected via JS -->
                    </div>
                    <div class="min-w-0">
                        <div id="attachment-name-text" class="truncate font-semibold text-white">file.jpg</div>
                        <div id="attachment-size-text" class="text-[11px] text-slate-400">0 KB</div>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="clearSelectedAttachment()"
                    class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors"
                    title="{{ __('Remove attachment') }}"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Input Bar -->
            <div class="p-3 sm:p-4 bg-slate-950/90 border-t border-slate-800/80 backdrop-blur-md shrink-0">
                <form id="message-form" onsubmit="sendMessage(event)" class="flex items-end gap-2 max-w-5xl mx-auto">
                    <!-- File upload trigger -->
                    <label
                        for="file-input"
                        class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-emerald-500/50 hover:bg-slate-800 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer shrink-0 flex items-center justify-center"
                        title="{{ __('Upload Image, Video, or File') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <input
                            type="file"
                            id="file-input"
                            name="attachment"
                            class="hidden"
                            accept="image/*,video/*,audio/*,application/pdf"
                            onchange="handleFileSelected(this)"
                        >
                    </label>

                    <!-- Text Message Input -->
                    <div class="flex-1 min-w-0 relative">
                        <textarea
                            id="message-input"
                            name="content"
                            rows="1"
                            placeholder="{{ __('Transmit encrypted message... (Shift+Enter for newline)') }}"
                            class="w-full bg-slate-900/90 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 resize-none max-h-32 min-h-[42px] transition-colors leading-relaxed chat-scroll"
                            onkeydown="handleTextareaKey(event)"
                            oninput="autoResizeTextarea(this)"
                        ></textarea>
                    </div>

                    <!-- Send Button -->
                    <button
                        type="submit"
                        id="send-btn"
                        class="p-2.5 sm:px-4 sm:py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 active:bg-emerald-600 text-slate-950 font-mono font-bold text-sm tracking-wider uppercase transition-all shadow-[0_0_15px_rgba(16,185,129,0.3)] flex items-center justify-center gap-1.5 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <span class="hidden sm:inline">{{ __('SEND') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- Intel Radar Slide-over Drawer -->
        <aside id="radar-drawer" class="fixed inset-y-0 right-0 w-full sm:w-[440px] bg-slate-950/95 border-l border-slate-800 backdrop-blur-xl z-30 transform translate-x-full transition-transform duration-300 flex flex-col shadow-2xl">
            <!-- Radar Header -->
            <div class="p-4 border-b border-slate-800 flex items-center justify-between font-mono shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping"></div>
                    <h2 class="font-bold text-sm text-cyan-300 tracking-wider">{{ __('GEOSPATIAL INTEL RADAR') }}</h2>
                </div>
                <button
                    type="button"
                    onclick="toggleRadar()"
                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Radar Content -->
            <div class="flex-1 overflow-y-auto chat-scroll p-4 space-y-4">
                <!-- Interactive Leaflet Map -->
                <div class="rounded-xl overflow-hidden border border-slate-800 relative">
                    <div id="intel-map" class="w-full h-56 bg-slate-900"></div>
                    <div class="absolute top-2 left-2 z-[400] bg-slate-950/80 border border-slate-700/80 px-2 py-1 rounded text-[10px] font-mono text-cyan-300 flex items-center gap-1.5 backdrop-blur-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span>{{ __('LIVE SATELLITE PLOT') }}</span>
                    </div>
                </div>

                <!-- GPS Enhancement Action -->
                <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between font-mono text-xs">
                    <div>
                        <div class="font-semibold text-white">{{ __('Browser GPS Coordinates') }}</div>
                        <div class="text-[11px] text-slate-400">{{ __('Transmit exact device latitude & longitude') }}</div>
                    </div>
                    <button
                        type="button"
                        id="gps-sync-btn"
                        onclick="syncBrowserGps()"
                        class="px-2.5 py-1.5 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-[11px] font-bold transition-all flex items-center gap-1 cursor-pointer shrink-0"
                    >
                        <span>{{ __('SYNC GPS') }}</span>
                    </button>
                </div>

                <!-- Active Operatives List -->
                <div>
                    <div class="font-mono text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>{{ __('Connected Nodes') }} (<span id="operatives-count">0</span>)</span>
                        <button type="button" onclick="loadRadarData()" class="text-[10px] text-cyan-400 hover:text-cyan-300">{{ __('Refresh') }}</button>
                    </div>
                    <div id="operatives-list" class="space-y-2">
                        <!-- Injected dynamically -->
                    </div>
                </div>

                <!-- Recent Access Audit Log -->
                <div>
                    <div class="font-mono text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        {{ __('Audit Trail / Access Log') }}
                    </div>
                    <div id="access-log-list" class="space-y-1.5 font-mono text-[11px]">
                        <!-- Injected dynamically -->
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Media Lightbox Modal -->
    <div id="lightbox-modal" class="fixed inset-0 bg-slate-950/95 backdrop-blur-xl z-50 hidden flex flex-col items-center justify-center p-4 cursor-pointer" onclick="closeLightbox()">
        <!-- Close & Action bar -->
        <div class="absolute top-4 right-4 flex items-center gap-3 z-10 font-mono" onclick="event.stopPropagation()">
            <a
                id="lightbox-download-link"
                href="#"
                download
                class="px-3 py-1.5 rounded-lg bg-slate-900/90 border border-slate-700 hover:border-emerald-500 text-slate-200 hover:text-emerald-400 text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>{{ __('DOWNLOAD') }}</span>
            </a>
            <button
                type="button"
                onclick="closeLightbox()"
                class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                title="Close Lightbox (Esc)"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Lightbox Media Stage -->
        <div class="max-w-5xl max-h-[85vh] w-full flex flex-col items-center justify-center cursor-default" onclick="event.stopPropagation()">
            <div id="lightbox-loader" class="hidden text-emerald-400 font-mono text-xs flex items-center gap-2 mb-2 animate-pulse">
                <svg class="w-4 h-4 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                <span>{{ app()->getLocale() === 'ru' ? 'ЗАГРУЗКА МЕДИА ДАННЫХ...' : 'DECRYPTING MEDIA PAYLOAD...' }}</span>
            </div>
            <img id="lightbox-img" src="" alt="Media preview" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl object-contain hidden border border-white/10">
            <video id="lightbox-video" src="" controls class="max-w-full max-h-[80vh] rounded-lg shadow-2xl hidden border border-white/10" preload="metadata"></video>
            <p id="lightbox-caption" class="text-xs text-slate-400 font-mono mt-3 text-center truncate max-w-lg"></p>
        </div>
    </div>

    <!-- Emergency Nuke Confirmation Modal -->
    <div id="nuke-modal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-rose-500/50 rounded-2xl p-6 shadow-[0_0_30px_rgba(244,63,94,0.3)] font-mono space-y-4">
            <div class="flex items-center gap-3 text-rose-400">
                <div class="p-2 rounded-xl bg-rose-950/80 border border-rose-500/40">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-base text-white tracking-wider">{{ __('CONFIRM EMERGENCY NUKE') }}</h3>
                    <p class="text-[11px] text-rose-400">{{ __('Irreversible Self-Destruct Action') }}</p>
                </div>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed">
                {{ __('Executing the Emergency Nuke will immediately:') }}
            </p>
            <ul class="text-xs text-slate-400 list-disc list-inside space-y-1 text-[11px]">
                <li>{{ __('Permanently wipe and destroy this channel') }} (<strong class="text-white">{{ $room->code }}</strong>)</li>
                <li>{{ __('Permanently delete every message and conversation') }}</li>
                <li>{{ __('Purge and erase all shared images, videos, and files from storage') }}</li>
                <li>{{ __('Revoke access tokens for all active participants') }}</li>
            </ul>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button
                    type="button"
                    onclick="closeNukeModal()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer"
                >
                    {{ __('CANCEL') }}
                </button>
                <form action="{{ route('rooms.nuke', ['room' => $room->code]) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-[0_0_15px_rgba(244,63,94,0.4)] cursor-pointer"
                    >
                        {{ __('EXECUTE PURGE') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Member Info Card Modal -->
    <div id="member-card-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4 font-sans">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 class="text-xs font-mono uppercase text-slate-400 tracking-wider">{{ __('Member Details') }}</h3>
                <button type="button" onclick="closeMemberCard()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border-2 border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold font-mono text-2xl shrink-0 shadow-lg shadow-emerald-950/50">
                    <img id="card-avatar-img" src="" class="w-full h-full object-cover hidden" alt="Member Avatar">
                    <span id="card-avatar"></span>
                </div>
                <div class="min-w-0">
                    <div id="card-name" class="font-bold text-white text-lg tracking-tight truncate"></div>
                    <div class="text-[11px] font-mono text-emerald-400/80 mt-0.5">{{ __('Member Profile') }}</div>
                </div>
            </div>
            <div class="space-y-2 pt-2 border-t border-slate-800/80 text-xs font-mono">
                <div class="flex items-center justify-between text-slate-300">
                    <span class="text-slate-500">{{ __('Age') }}:</span>
                    <span id="card-age" class="text-slate-200 font-bold"></span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span class="text-slate-500">{{ __('Birthday') }}:</span>
                    <span id="card-birthday" class="text-slate-200"></span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span class="text-slate-500">{{ __('Gender') }}:</span>
                    <span id="card-gender" class="text-slate-200 notranslate" translate="no"></span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span class="text-slate-500">{{ __('Location') }}:</span>
                    <span id="card-location" class="text-slate-200"></span>
                </div>
                <div id="card-bio-container" class="pt-2 border-t border-slate-800/50 hidden">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-slate-500 block">{{ __('Bio') }}:</span>
                        <span id="card-bio-private-badge" class="hidden text-[9px] px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 {{ __('Private') }}</span>
                    </div>
                    <p id="card-bio" class="text-slate-300 font-sans text-xs italic"></p>
                </div>
            </div>
            <div class="pt-2 text-center">
                <button type="button" onclick="closeMemberCard()" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-mono cursor-pointer transition-colors">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Member / User Profile Modal -->
    @if(Auth::check())
    <div id="profile-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto p-3 sm:p-4 flex items-center justify-center">
        <div class="w-full max-w-md my-auto bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 sm:p-5 pb-3 sm:pb-4 border-b border-slate-800 shrink-0 bg-slate-900">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold font-mono text-sm">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">{{ __('Profile Settings') }}</h3>
                    </div>
                </div>
                <button type="button" onclick="closeProfileModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <form id="profile-form" onsubmit="saveProfile(event)" class="flex flex-col flex-1 min-h-0">
                @csrf
                <input type="hidden" name="room_id" value="{{ $room->id }}">

                <div class="overflow-y-auto p-4 sm:p-5 space-y-3.5 text-xs font-sans flex-1 modal-scroll">
                    <div id="profile-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>

                    <!-- Custom Avatar Uploader -->
                    <div class="flex items-center gap-3.5 p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                        <div class="relative group shrink-0">
                            <div id="profile-avatar-preview-wrap" class="w-14 h-14 rounded-2xl overflow-hidden bg-emerald-500/20 border-2 border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold font-mono text-lg">
                                @if(Auth::user()->avatar_path)
                                    <img id="profile-avatar-preview-img" src="{{ Auth::user()->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                    <span id="profile-avatar-preview-initial" class="hidden">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                                @else
                                    <img id="profile-avatar-preview-img" src="" class="w-full h-full object-cover hidden" alt="">
                                    <span id="profile-avatar-preview-initial">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="text-[11px] font-semibold text-white">{{ __('Profile Picture') }}</div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick="document.getElementById('avatar-file-input').click()"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-medium cursor-pointer transition-colors"
                                >
                                    {{ __('Upload Photo') }}
                                </button>
                                <button
                                    type="button"
                                    id="remove-avatar-btn"
                                    onclick="markAvatarForRemoval()"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium cursor-pointer transition-colors {{ Auth::user()->avatar_path ? '' : 'hidden' }}"
                                >
                                    {{ __('Remove') }}
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-500 font-mono">{{ __('JPG, PNG, WEBP, GIF (Max 5MB)') }}</p>
                        </div>
                        <input type="file" id="avatar-file-input" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                        <input type="hidden" id="remove-avatar-flag" name="remove_avatar" value="0">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Display Name / Username') }} <span class="text-rose-400">*</span></label>
                        <input
                            type="text"
                            name="name"
                            required
                            value="{{ Auth::user()->name }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Email Address') }} <span class="text-rose-400">*</span></label>
                        <input
                            type="email"
                            name="email"
                            required
                            value="{{ Auth::user()->email }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">{{ __('Birthday') }}</label>
                            <input
                                type="date"
                                name="birthday"
                                value="{{ Auth::user()->birthday?->format('Y-m-d') }}"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:outline-none focus:border-emerald-500"
                            >
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">{{ __('Gender') }}</label>
                            <select
                                name="gender"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                            >
                                <option value="">{{ __('Prefer not to say') }}</option>
                                <option value="Male" {{ Auth::user()->gender === 'Male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                                <option value="Female" {{ Auth::user()->gender === 'Female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                                <option value="Non-binary" {{ Auth::user()->gender === 'Non-binary' ? 'selected' : '' }}>{{ __('Non-binary') }}</option>
                                <option value="Other" {{ Auth::user()->gender === 'Other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="font-medium text-slate-300">{{ __('Location') }}</label>
                            <button
                                type="button"
                                onclick="detectProfileGps()"
                                class="text-[10px] text-cyan-400 hover:text-cyan-300 font-mono flex items-center gap-1 cursor-pointer transition-colors"
                                title="{{ __('Auto-detect location via browser GPS') }}"
                            >
                                <span>📍</span>
                                <span id="profile-detect-gps-text">{{ __('Detect GPS') }}</span>
                            </button>
                        </div>
                        <input
                            type="text"
                            name="location"
                            id="profile-input-location"
                            placeholder="e.g. Rome, Italy"
                            value="{{ Auth::user()->location }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Bio / Status') }}</label>
                        <textarea
                            name="bio"
                            rows="2"
                            placeholder="A brief note about yourself..."
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                        >{{ Auth::user()->bio }}</textarea>
                    </div>

                    <!-- Language Preference Setting -->
                    <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block font-medium text-slate-300 text-xs flex items-center gap-1.5">
                                <span>🌐</span>
                                <span>{{ __('Language Preference') }}</span>
                            </label>
                            @php
                                $detectedLang = Auth::user()->resolveLocationLocale();
                                $langMap = [
                                    'en' => 'English (EN)',
                                    'ru' => 'Russian (RU)',
                                    'fr' => 'French (FR)',
                                    'it' => 'Italian (IT)',
                                ];
                            @endphp
                            <span class="text-[10px] font-mono text-emerald-400/80">
                                {{ __('Detected') }}: {{ $langMap[$detectedLang] ?? strtoupper($detectedLang) }}
                            </span>
                        </div>
                        <select
                            name="preferred_locale"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500 font-sans cursor-pointer"
                        >
                            <option value="auto" {{ empty(Auth::user()->preferred_locale) ? 'selected' : '' }}>
                                🌐 {{ __('Auto-detect from Registered Location') }} ({{ $langMap[$detectedLang] ?? strtoupper($detectedLang) }})
                            </option>
                            <option value="en" {{ Auth::user()->preferred_locale === 'en' ? 'selected' : '' }}>
                                🇬🇧 English (EN)
                            </option>
                            <option value="ru" {{ Auth::user()->preferred_locale === 'ru' ? 'selected' : '' }}>
                                🇷🇺 Russian (RU) - Русский
                            </option>
                            <option value="fr" {{ Auth::user()->preferred_locale === 'fr' ? 'selected' : '' }}>
                                🇫🇷 French (FR) - Français
                            </option>
                            <option value="it" {{ Auth::user()->preferred_locale === 'it' ? 'selected' : '' }}>
                                🇮🇹 Italian (IT) - Italiano
                            </option>
                        </select>
                        <p class="text-[10px] text-slate-400 leading-snug">
                            {{ __('Selecting a preferred language overrides your registered location language across all devices.') }}
                        </p>
                    </div>

                    <!-- Privacy & Visibility Settings -->
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-semibold text-white uppercase font-mono tracking-wider flex items-center gap-1.5">
                                <span>🔒</span>
                                <span>{{ __('Privacy & Visibility') }}</span>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500">{{ __('Member Restrictions') }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-snug">
                            {{ __('Choose which details are concealed when other members click your name in chat. Hiding age conceals your birth year (Month & Day remain visible unless Birthday is also hidden). Administrators retain full visibility.') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-xs">
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_age"
                                    value="1"
                                    {{ Auth::user()->hide_age ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Age') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_birthday"
                                    value="1"
                                    {{ Auth::user()->hide_birthday ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Birthday') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_location"
                                    value="1"
                                    {{ Auth::user()->hide_location ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Location') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_bio"
                                    value="1"
                                    {{ Auth::user()->hide_bio ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Bio') }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('New Password') }} <span class="text-slate-500 text-[10px]">({{ __('leave blank to keep current') }})</span></label>
                        <input
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div class="pt-1">
                        <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800 hover:border-emerald-500/40 cursor-pointer transition-colors select-none">
                            <input
                                type="checkbox"
                                name="email_notifications"
                                value="1"
                                {{ Auth::user()->email_notifications ? 'checked' : '' }}
                                class="mt-0.5 rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                            >
                            <div class="text-xs">
                                <span class="font-medium text-white block">{{ __('Email Notifications') }}</span>
                                <span class="text-slate-400 text-[11px] block mt-0.5 leading-snug">{{ __('Receive email notifications when new messages are posted in this channel.') }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="p-3.5 sm:p-4 border-t border-slate-800 bg-slate-950/70 flex items-center justify-end gap-2 font-mono shrink-0">
                    <button
                        type="button"
                        onclick="closeProfileModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer text-xs"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        id="save-profile-btn"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer text-xs"
                    >
                        {{ __('Save Profile') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Leaflet JS for Intel Radar -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // Configuration and State
        const ROOM_CODE = "{{ $room->code }}";
        const SESSION_ID = "{{ $sessionId }}";
        const CURRENT_LOCALE = "{{ app()->getLocale() }}";
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const ALLOWED_LANGUAGES = @json($room->effectiveAllowedLanguages());
        const CURRENT_USER_NAME = @json(Auth::user()?->name);

        // Client localization strings
        const I18N = {
            active: "{{ __('Active') }}",
            expand: "{{ __('EXPAND') }}",
            expandVideo: "{{ __('Expand Video') }}",
            translate: "{{ __('Translate') }}",
            translating: "{{ __('Translating...') }}",
            original: "{{ __('Original') }}",
            admin: "{{ __('Admin') }}",
            member: "{{ __('Member') }}",
            guest: "{{ __('Guest') }}",
            viewProfile: "{{ __('Click to view profile') }}",
            commandCenter: "{{ __('Command Center') }}",
            localNetwork: "{{ __('Local Network') }}",
            noOperatives: "{{ __('No active operatives detected.') }}",
            justNow: "{{ __('Just now') }}",
            syncSuccess: "{{ __('✓ GPS SYNCED') }}",
            acquiring: "{{ __('ACQUIRING...') }}",
            closeLightbox: "{{ __('Close Lightbox (Esc)') }}",
            locationHidden: "{{ __('Location Hidden') }}",
            hidden: "{{ __('Hidden') }}"
        };

        const GENDER_LABELS = {
            'Male': "{{ __('Male') }}",
            'Female': "{{ __('Female') }}",
            'Non-binary': "{{ __('Non-binary') }}",
            'Other': "{{ __('Other') }}"
        };

        let lastMessageId = 0;
        let isPolling = false;
        let soundEnabled = true;
        let audioContext = null;
        let leafletMap = null;
        let mapMarkers = [];
        let radarOpen = false;
        let selectedFile = null;

        // Initialize Web Audio API synthesizer chimes
        function getAudioContext() {
            if (!audioContext) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    audioContext = new AudioCtx();
                }
            }
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            return audioContext;
        }

        function playChime(type = 'receive') {
            if (!soundEnabled) return;
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);

                const now = ctx.currentTime;
                if (type === 'send') {
                    // Crisp upward high-tech chirp
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(587.33, now); // D5
                    osc.frequency.exponentialRampToValueAtTime(880, now + 0.1); // A5
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
                    osc.start(now);
                    osc.stop(now + 0.12);
                } else {
                    // Soft dual-frequency reception chime
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(440, now); // A4
                    osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.08); // E5
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
                    osc.start(now);
                    osc.stop(now + 0.2);
                }
            } catch (err) {
                // Audio synthesis error or autoplay blocked
            }
        }

        function toggleSound() {
            soundEnabled = !soundEnabled;
            document.getElementById('sound-icon-on').classList.toggle('hidden', !soundEnabled);
            document.getElementById('sound-icon-off').classList.toggle('hidden', soundEnabled);
        }

        // Live Message Fetch Loop
        async function fetchMessages() {
            if (isPolling) return;
            isPolling = true;

            try {
                const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/messages?after_id=${lastMessageId}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (res.status === 410) {
                    // Channel was terminated / nuked
                    alert(CURRENT_LOCALE === 'ru' ? 'Этот защищённый канал был уничтожен.' : 'This secure channel was terminated or self-destructed.');
                    window.location.href = '/';
                    return;
                }

                if (res.status === 403) {
                    window.location.reload();
                    return;
                }

                if (res.ok) {
                    const data = await res.json();
                    if (data.messages && data.messages.length > 0) {
                        let hasIncoming = false;
                        data.messages.forEach(msg => {
                            if (msg.id > lastMessageId) {
                                appendMessage(msg);
                                lastMessageId = Math.max(lastMessageId, msg.id);
                                if (!msg.is_self) {
                                    hasIncoming = true;
                                }
                            }
                        });
                        if (hasIncoming) {
                            playChime('receive');
                        }
                        scrollToBottom();
                    }

                    if (data.active_count !== undefined) {
                        const countText = data.active_count === 1 ? `1 ${I18N.active}` : `${data.active_count} ${I18N.active}`;
                        document.getElementById('active-count-badge').textContent = countText;
                    }
                }
            } catch (err) {
                console.warn('Comms sync interrupted:', err);
            } finally {
                isPolling = false;
            }
        }

        // Render Message Card in DOM
        function appendMessage(msg) {
            const container = document.getElementById('messages-list');
            const isSelf = msg.is_self;

            const wrapper = document.createElement('div');
            wrapper.className = `flex flex-col ${isSelf ? 'items-end' : 'items-start'} mb-3 transition-opacity duration-300`;
            wrapper.id = `msg-${msg.id}`;

            // Meta line above bubble
            const meta = document.createElement('div');
            meta.className = `flex items-center gap-1.5 text-[11px] font-mono text-slate-400 mb-1 px-1`;

            const memberData = {
                name: msg.sender_name,
                avatar_url: msg.sender_avatar_url,
                age: msg.sender_age,
                birthday: msg.sender_birthday,
                gender: msg.sender_gender,
                location: msg.sender_location,
                bio: msg.sender_bio,
                city: msg.city,
                country: msg.country,
                flag: msg.flag
            };

            const avatarMarkup = msg.sender_avatar_url
                ? `<img src="${escapeHtml(msg.sender_avatar_url)}" class="w-4 h-4 rounded-full object-cover border border-slate-700/80 shrink-0" alt="">`
                : `<span class="w-4 h-4 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-[9px] font-bold text-slate-300 shrink-0">${escapeHtml((msg.sender_name || 'G').charAt(0).toUpperCase())}</span>`;

            const nameBtn = document.createElement('button');
            nameBtn.type = 'button';
            nameBtn.className = `font-semibold ${isSelf ? 'text-emerald-400' : 'text-slate-200'} flex items-center gap-1.5 hover:underline cursor-pointer`;
            nameBtn.title = (memberData.age || memberData.location) ? I18N.viewProfile : '';
            nameBtn.innerHTML = `
                ${avatarMarkup}
                <span>${escapeHtml(msg.sender_name)}</span>
            `;
            nameBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openMemberCard(memberData);
            });

            meta.appendChild(nameBtn);

            const sep = document.createElement('span');
            sep.textContent = '•';
            meta.appendChild(sep);

            const timeSpan = document.createElement('span');
            timeSpan.className = 'text-slate-500';
            timeSpan.textContent = msg.created_at_time;
            meta.appendChild(timeSpan);

            // Bubble container
            const bubble = document.createElement('div');
            bubble.className = `max-w-[85%] sm:max-w-[70%] rounded-2xl p-3.5 shadow-lg ${
                isSelf
                    ? 'bg-gradient-to-br from-emerald-600/90 to-teal-700/90 text-white rounded-br-none border border-emerald-400/30'
                    : 'bg-slate-900/90 text-slate-100 rounded-bl-none border border-slate-800 backdrop-blur-md'
            }`;

            let bubbleContent = '';

            // Render Attachment
            if (msg.attachment_url) {
                const mediaUrl = normalizeAttachmentUrl(msg.attachment_url);
                const mediaName = msg.attachment_name || 'attachment';

                if (msg.attachment_type === 'image') {
                    bubbleContent += `
                        <div
                            class="mb-2 rounded-xl overflow-hidden cursor-pointer group relative border border-white/10 bg-black/40"
                            onclick="openLightbox(this.dataset.url, 'image', this.dataset.name)"
                            data-url="${escapeHtml(mediaUrl)}"
                            data-name="${escapeHtml(mediaName)}"
                        >
                            <img src="${escapeHtml(mediaUrl)}" alt="${escapeHtml(mediaName)}" class="max-h-80 w-auto rounded-lg object-cover transition-transform group-hover:scale-105 duration-200">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-mono gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
                                <span>${I18N.expand}</span>
                            </div>
                        </div>
                    `;
                } else if (msg.attachment_type === 'video') {
                    bubbleContent += `
                        <div class="mb-2 rounded-xl overflow-hidden border border-white/10 bg-black/60 relative group">
                            <video src="${escapeHtml(mediaUrl)}" controls class="max-h-80 w-full rounded-lg" preload="metadata"></video>
                            <button
                                type="button"
                                onclick="openLightbox(this.dataset.url, 'video', this.dataset.name)"
                                data-url="${escapeHtml(mediaUrl)}"
                                data-name="${escapeHtml(mediaName)}"
                                class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-black/80 hover:bg-black text-white text-[11px] flex items-center gap-1 font-mono transition-opacity opacity-0 group-hover:opacity-100 backdrop-blur-sm border border-white/10 cursor-pointer"
                                title="Expand Video"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                <span>${I18N.expand}</span>
                            </button>
                        </div>
                    `;
                } else if (msg.attachment_type === 'audio') {
                    bubbleContent += `
                        <div class="mb-2 p-2 rounded-xl bg-black/30 border border-white/10">
                            <audio src="${escapeHtml(mediaUrl)}" controls class="w-full h-8"></audio>
                        </div>
                    `;
                } else {
                    bubbleContent += `
                        <a href="${escapeHtml(mediaUrl)}" download="${escapeHtml(mediaName)}" class="mb-2 p-2.5 rounded-xl bg-black/30 border border-white/10 flex items-center gap-3 hover:bg-black/50 transition-colors font-mono text-xs">
                            <svg class="w-6 h-6 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-white font-medium">${escapeHtml(mediaName)}</div>
                                <div class="text-[10px] text-slate-400">${escapeHtml(msg.formatted_size || '')}</div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        </a>
                    `;
                }
            }

            // Text Content + Translation Box
            if (msg.content) {
                bubbleContent += `
                    <div id="msg-text-${msg.id}" class="text-sm leading-relaxed break-words whitespace-pre-wrap">${escapeHtml(msg.content)}</div>
                    <div id="msg-trans-${msg.id}" class="hidden mt-2 pt-2 border-t border-white/10 text-xs font-sans text-cyan-200 bg-cyan-950/30 p-2 rounded-lg" data-current-lang="">
                        <div class="text-[10px] font-mono text-cyan-400 flex items-center gap-1 mb-1">
                            <span class="trans-flag">🌐</span>
                            <span class="trans-label font-bold">TRANSLATION:</span>
                        </div>
                        <div class="trans-body leading-relaxed break-words whitespace-pre-wrap"></div>
                    </div>
                `;
            }

            // Allowed Translate Pills scoped to channel configuration
            const allowedLangs = Array.isArray(ALLOWED_LANGUAGES) && ALLOWED_LANGUAGES.length > 0 ? ALLOWED_LANGUAGES : ['en', 'ru', 'fr', 'it'];
            let translateButtons = '';
            allowedLangs.forEach(langCode => {
                const upper = langCode.toUpperCase();
                translateButtons += `<button type="button" onclick="translateMessage(${msg.id}, '${langCode}', this)" class="px-1.5 py-0.5 rounded hover:bg-white/10 hover:text-white transition-colors cursor-pointer" title="${upper}">${upper}</button>`;
            });

            // Footer ticks & Translate Pills
            bubbleContent += `
                <div class="flex items-center justify-between gap-2 mt-2 pt-1.5 text-[10px] font-mono opacity-90 border-t border-white/10">
                    ${(msg.content && allowedLangs.length > 0) ? `
                        <div class="flex items-center gap-1 text-slate-400 flex-wrap">
                            <span class="text-[11px]">🌐</span>
                            <span class="text-[10px] text-slate-400 mr-0.5">${I18N.translate || 'Translate'}:</span>
                            ${translateButtons}
                        </div>
                    ` : '<span></span>'}
                    <div class="flex items-center gap-1">
                        <span>${msg.created_at_time}</span>
                        ${isSelf ? '<svg class="w-3 h-3 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7m-4 4l4 4" /></svg>' : ''}
                    </div>
                </div>
            `;

            bubble.innerHTML = bubbleContent;
            wrapper.appendChild(meta);
            wrapper.appendChild(bubble);
            container.appendChild(wrapper);
        }

        // Inline Message Translation Action (supporting RU, FR, IT, EN)
        async function translateMessage(msgId, targetLang, btn) {
            const textEl = document.getElementById(`msg-text-${msgId}`);
            const transBox = document.getElementById(`msg-trans-${msgId}`);
            if (!textEl || !transBox) return;

            // If already shown for this target language, toggle off
            if (!transBox.classList.contains('hidden') && transBox.dataset.currentLang === targetLang) {
                transBox.classList.add('hidden');
                return;
            }

            const originalText = textEl.textContent.trim();
            const originalBtnText = btn.textContent;
            btn.textContent = '...';

            try {
                const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/translate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        text: originalText,
                        target: targetLang
                    })
                });

                btn.textContent = originalBtnText;

                if (res.ok) {
                    const data = await res.json();
                    const transBody = transBox.querySelector('.trans-body');
                    const transFlag = transBox.querySelector('.trans-flag');
                    const transLabel = transBox.querySelector('.trans-label');

                    const flagMap = { 'ru': '🇷🇺', 'fr': '🇫🇷', 'it': '🇮🇹', 'en': '🇬🇧' };
                    const lang = data.target_lang || targetLang;

                    if (transFlag) transFlag.textContent = flagMap[lang] || '🌐';
                    if (transLabel) transLabel.textContent = `TRANSLATION (${lang.toUpperCase()}):`;

                    transBody.textContent = data.translated_text || originalText;
                    transBox.dataset.currentLang = lang;
                    transBox.classList.remove('hidden');
                } else {
                    alert('Translation service currently busy. Please retry.');
                }
            } catch (err) {
                console.warn('Translation error:', err);
                btn.textContent = originalBtnText;
            }
        }

        // Send Message & Attachments
        async function sendMessage(event) {
            event.preventDefault();
            const textInput = document.getElementById('message-input');
            const sendBtn = document.getElementById('send-btn');
            const content = textInput.value.trim();

            if (!content && !selectedFile) {
                return;
            }

            sendBtn.disabled = true;

            const formData = new FormData();
            if (content) {
                formData.append('content', content);
            }
            if (selectedFile) {
                formData.append('attachment', selectedFile);
            }

            try {
                const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/messages`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.message) {
                        appendMessage(data.message);
                        lastMessageId = Math.max(lastMessageId, data.message.id);
                        playChime('send');
                        scrollToBottom();
                    }

                    textInput.value = '';
                    textInput.style.height = 'auto';
                    clearSelectedAttachment();
                } else {
                    const errData = await res.json().catch(() => ({}));
                    alert(errData.error || errData.message || 'Failed to transmit message.');
                }
            } catch (err) {
                console.error('Send error:', err);
                alert('Transmission interrupted. Check connection.');
            } finally {
                sendBtn.disabled = false;
                textInput.focus();
            }
        }

        // Auto-resize textarea
        function autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }

        function handleTextareaKey(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                document.getElementById('message-form').requestSubmit();
            }
        }

        function scrollToBottom() {
            const stream = document.getElementById('message-stream');
            stream.scrollTop = stream.scrollHeight;
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // File Selection & Drag-and-drop
        function handleFileSelected(input) {
            if (input.files && input.files[0]) {
                setFileAttachment(input.files[0]);
            }
        }

        function setFileAttachment(file) {
            selectedFile = file;
            const bar = document.getElementById('attachment-preview-bar');
            const nameEl = document.getElementById('attachment-name-text');
            const sizeEl = document.getElementById('attachment-size-text');
            const thumbEl = document.getElementById('attachment-thumb-container');

            nameEl.textContent = file.name;
            sizeEl.textContent = formatBytes(file.size);
            thumbEl.innerHTML = '';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'w-full h-full object-cover';
                img.src = URL.createObjectURL(file);
                thumbEl.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                thumbEl.innerHTML = '<span class="text-cyan-400 font-mono text-xs">VID</span>';
            } else {
                thumbEl.innerHTML = '<span class="text-slate-400 font-mono text-xs">DOC</span>';
            }

            bar.classList.remove('hidden');
        }

        function clearSelectedAttachment() {
            selectedFile = null;
            document.getElementById('file-input').value = '';
            document.getElementById('attachment-preview-bar').classList.add('hidden');
        }

        function formatBytes(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        // Drag & Drop Handling over workspace
        const dropOverlay = document.getElementById('drop-overlay');
        window.addEventListener('dragenter', (e) => {
            e.preventDefault();
            dropOverlay.classList.remove('opacity-0', 'pointer-events-none');
        });
        dropOverlay.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
        dropOverlay.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropOverlay.classList.add('opacity-0', 'pointer-events-none');
        });
        dropOverlay.addEventListener('drop', (e) => {
            e.preventDefault();
            dropOverlay.classList.add('opacity-0', 'pointer-events-none');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                setFileAttachment(e.dataTransfer.files[0]);
            }
        });

        // Clipboard Paste Support (Screenshot uploading!)
        window.addEventListener('paste', (e) => {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (let item of items) {
                if (item.kind === 'file') {
                    const blob = item.getAsFile();
                    if (blob) {
                        setFileAttachment(blob);
                        break;
                    }
                }
            }
        });

        // Lightbox Media URL Normalizer
        function normalizeAttachmentUrl(url) {
            if (!url) return '';
            try {
                if (url.includes('/storage/')) {
                    const idx = url.indexOf('/storage/');
                    return url.substring(idx);
                }
            } catch (_) {}
            return url;
        }

        // Lightbox
        function openLightbox(url, type, name) {
            const modal = document.getElementById('lightbox-modal');
            const img = document.getElementById('lightbox-img');
            const video = document.getElementById('lightbox-video');
            const caption = document.getElementById('lightbox-caption');
            const dl = document.getElementById('lightbox-download-link');
            const loader = document.getElementById('lightbox-loader');

            const cleanUrl = normalizeAttachmentUrl(url);

            img.classList.add('hidden');
            video.classList.add('hidden');
            if (loader) loader.classList.remove('hidden');

            try {
                video.pause();
                video.src = '';
            } catch (_) {}

            dl.href = cleanUrl;
            dl.download = name || 'media';
            caption.textContent = name || '';

            if (type === 'image') {
                img.onload = () => {
                    if (loader) loader.classList.add('hidden');
                    img.classList.remove('hidden');
                };
                img.onerror = () => {
                    if (loader) loader.classList.add('hidden');
                    caption.textContent = (CURRENT_LOCALE === 'ru' ? 'Ошибка загрузки изображения: ' : 'Failed to load image: ') + (name || '');
                };
                img.src = cleanUrl;
            } else if (type === 'video') {
                video.onloadeddata = () => {
                    if (loader) loader.classList.add('hidden');
                    video.classList.remove('hidden');
                };
                video.onerror = () => {
                    if (loader) loader.classList.add('hidden');
                    caption.textContent = (CURRENT_LOCALE === 'ru' ? 'Ошибка воспроизведения видео: ' : 'Failed to play video: ') + (name || '');
                };
                video.src = cleanUrl;
                video.classList.remove('hidden');
                if (loader) loader.classList.add('hidden');
                video.play().catch(() => {});
            }

            modal.classList.remove('hidden');
        }

        function closeLightbox() {
            const modal = document.getElementById('lightbox-modal');
            const img = document.getElementById('lightbox-img');
            const video = document.getElementById('lightbox-video');
            try {
                video.pause();
                video.src = '';
            } catch (_) {}
            img.src = '';
            img.classList.add('hidden');
            video.classList.add('hidden');
            modal.classList.add('hidden');
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLightbox();
                closeNukeModal();
            }
        });

        // Nuke Modal
        function openNukeModal() {
            document.getElementById('nuke-modal').classList.remove('hidden');
        }
        function closeNukeModal() {
            document.getElementById('nuke-modal').classList.add('hidden');
        }

        // Copy Channel Link
        function copyChannelLink() {
            const url = window.location.origin + `/c/${encodeURIComponent(ROOM_CODE)}`;
            function onSuccess() {
                const btn = document.getElementById('copy-link-btn');
                if (btn) {
                    btn.innerHTML = '<span class="text-emerald-400 text-xs">✓</span>';
                    setTimeout(() => {
                        btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`;
                    }, 1500);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(onSuccess).catch(() => fallbackRoomCopy(url, onSuccess));
            } else {
                fallbackRoomCopy(url, onSuccess);
            }
        }

        function fallbackRoomCopy(text, callback) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-999999px';
                textarea.style.top = '-999999px';
                textarea.setAttribute('readonly', '');
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (ok && callback) callback();
            } catch (e) {
                prompt('Copy channel link:', text);
            }
        }

        // Intel Radar & Leaflet Map
        function toggleRadar() {
            const drawer = document.getElementById('radar-drawer');
            radarOpen = !radarOpen;
            if (radarOpen) {
                drawer.classList.remove('translate-x-full');
                initIntelMap();
                loadRadarData();
            } else {
                drawer.classList.add('translate-x-full');
            }
        }

        function initIntelMap() {
            if (!leafletMap) {
                leafletMap = L.map('intel-map', {
                    attributionControl: false,
                    zoomControl: false
                }).setView([20, 0], 2);

                const cartoKey = @json(config('services.carto.key'));
                const tileUrl = 'https://{s}.basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png' + (cartoKey ? '?key=' + encodeURIComponent(cartoKey) : '');

                L.tileLayer(tileUrl, {
                    maxZoom: 18,
                    subdomains: 'abcd',
                }).addTo(leafletMap);

                L.control.zoom({ position: 'bottomright' }).addTo(leafletMap);
            }
            setTimeout(() => {
                leafletMap.invalidateSize();
            }, 300);
        }

        async function loadRadarData() {
            try {
                const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/radar`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    renderRadarView(data);
                }
            } catch (err) {
                console.warn('Radar fetch error:', err);
            }
        }

        function renderRadarView(data) {
            document.getElementById('operatives-count').textContent = data.total_active || 0;

            // Clear old map markers
            mapMarkers.forEach(m => leafletMap.removeLayer(m));
            mapMarkers = [];

            const listEl = document.getElementById('operatives-list');
            listEl.innerHTML = '';

            const bounds = [];

            if (data.operatives && data.operatives.length > 0) {
                data.operatives.forEach(op => {
                    // Add card
                    const card = document.createElement('div');
                    card.className = 'p-3 rounded-xl bg-slate-900/80 border border-slate-800/80 font-mono text-xs space-y-1.5';

                    let locationHtml = '';
                    if (op.location_hidden) {
                        if (op.city || op.country) {
                            const locStr = [op.city, op.country].filter(Boolean).join(', ');
                            locationHtml = `
                                <span>${op.flag || '📍'}</span>
                                <span class="text-slate-300">${escapeHtml(locStr)}</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 ${I18N.hidden || 'Hidden'}</span>
                            `;
                        } else {
                            locationHtml = `
                                <span>🔒</span>
                                <span class="text-slate-500 italic">${I18N.locationHidden || 'Location Hidden'}</span>
                            `;
                        }
                    } else if (op.city || op.country) {
                        const locStr = [op.city, op.country].filter(Boolean).join(', ');
                        locationHtml = `
                            <span>${op.flag || '🌐'}</span>
                            <span>${escapeHtml(locStr)}</span>
                        `;
                    } else {
                        locationHtml = `
                            <span>${op.flag || '🌐'}</span>
                            <span class="text-slate-500">${escapeHtml('Unknown')}</span>
                        `;
                    }

                    card.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-emerald-400">${escapeHtml(op.alias)}</span>
                            <span class="text-[10px] text-slate-400">${escapeHtml(op.last_seen)}</span>
                        </div>
                        <div class="text-[11px] text-slate-300 flex items-center gap-1.5 flex-wrap">
                            ${locationHtml}
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1 border-t border-slate-800">
                            <span>IP: ${escapeHtml(op.ip_address || '—')}</span>
                            <span>${escapeHtml(op.isp || 'Local')}</span>
                        </div>
                    `;
                    listEl.appendChild(card);

                    // Add map marker if coordinates exist
                    if (op.latitude && op.longitude && leafletMap) {
                        const marker = L.circleMarker([op.latitude, op.longitude], {
                            radius: 7,
                            fillColor: '#10b981',
                            color: '#34d399',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.8
                        }).addTo(leafletMap);

                        marker.bindPopup(`
                            <strong>${escapeHtml(op.alias)}</strong><br>
                            ${op.flag || '🌐'} ${escapeHtml(op.city || '')}, ${escapeHtml(op.country || '')}<br>
                            <span style="color:#64748b">IP: ${escapeHtml(op.ip_address)}</span>
                        `);

                        mapMarkers.push(marker);
                        bounds.push([op.latitude, op.longitude]);
                    }
                });

                if (bounds.length > 0 && leafletMap) {
                    if (bounds.length === 1) {
                        leafletMap.setView(bounds[0], 6);
                    } else {
                        leafletMap.fitBounds(bounds, { padding: [20, 20] });
                    }
                }
            } else {
                listEl.innerHTML = `<div class="text-xs font-mono text-slate-500 p-2">${I18N.noOperatives}</div>`;
            }

            // Access log list
            const auditEl = document.getElementById('access-log-list');
            auditEl.innerHTML = '';
            if (data.recent_entries && data.recent_entries.length > 0) {
                data.recent_entries.forEach(entry => {
                    const row = document.createElement('div');
                    row.className = 'p-2 rounded-lg bg-slate-900/40 border border-slate-800/50 flex items-center justify-between text-slate-400';

                    let entryLoc = '';
                    if (entry.location_hidden && !entry.city) {
                        entryLoc = `<span class="text-slate-500 italic text-[10px]">${I18N.locationHidden || 'Location Hidden'}</span>`;
                    } else if (entry.city || entry.country) {
                        entryLoc = `<span class="text-slate-500 truncate">${escapeHtml([entry.city, entry.country].filter(Boolean).join(', '))}</span>`;
                    }

                    row.innerHTML = `
                        <div class="flex items-center gap-1.5 truncate">
                            <span>${entry.flag || '🌐'}</span>
                            <span class="text-slate-300 font-semibold truncate">${escapeHtml(entry.alias)}</span>
                            ${entryLoc}
                        </div>
                        <span class="text-[10px] text-slate-500 shrink-0 ml-2">${escapeHtml(entry.human_time)}</span>
                    `;
                    auditEl.appendChild(row);
                });
            }
        }

        // Browser GPS sync
        function syncBrowserGps() {
            if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                alert(CURRENT_LOCALE === 'ru'
                    ? 'Синхронизация GPS требует защищенного соединения HTTPS.'
                    : 'GPS sync requires a secure HTTPS connection.');
                return;
            }

            if (!navigator.geolocation) {
                alert(CURRENT_LOCALE === 'ru' ? 'Геолокация не поддерживается вашим браузером.' : 'Geolocation is not supported by your browser.');
                return;
            }

            const btn = document.getElementById('gps-sync-btn');
            btn.innerHTML = `<span>${I18N.acquiring}</span>`;

            const sendCoordinates = async (pos) => {
                try {
                    const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/gps`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude
                        })
                    });
                    if (res.ok) {
                        btn.innerHTML = `<span class="text-emerald-400">${I18N.syncSuccess}</span>`;
                        loadRadarData();
                        setTimeout(() => {
                            btn.innerHTML = `<span>${CURRENT_LOCALE === 'ru' ? 'СИНХР. GPS' : 'SYNC GPS'}</span>`;
                        }, 2000);
                    }
                } catch (e) {
                    btn.innerHTML = `<span>${CURRENT_LOCALE === 'ru' ? 'СИНХР. GPS' : 'SYNC GPS'}</span>`;
                }
            };

            const handleError = (err) => {
                console.warn('Geolocation high accuracy failed, falling back to standard accuracy...', err);
                navigator.geolocation.getCurrentPosition(
                    sendCoordinates,
                    (finalErr) => {
                        console.error('Geolocation final error:', finalErr);
                        let msg = 'GPS Access was denied or unavailable.';
                        if (finalErr.code === 1) {
                            msg = 'GPS permission denied. Please allow location access in your browser / macOS System Settings.';
                        } else if (finalErr.code === 2) {
                            msg = 'Position unavailable. Please ensure macOS Location Services and Wi-Fi are turned on.';
                        } else if (finalErr.code === 3) {
                            msg = 'GPS acquisition timed out. Please try again.';
                        }
                        if (CURRENT_LOCALE === 'ru') {
                            msg = 'Доступ к GPS отклонен или недоступен. Проверьте системные настройки геолокации.';
                        }
                        alert(msg);
                        btn.innerHTML = `<span>${CURRENT_LOCALE === 'ru' ? 'СИНХР. GPS' : 'SYNC GPS'}</span>`;
                    },
                    { enableHighAccuracy: false, timeout: 12000 }
                );
            };

            navigator.geolocation.getCurrentPosition(
                sendCoordinates,
                handleError,
                { enableHighAccuracy: true, timeout: 5000 }
            );
        }

        // Profile Modal
        function openProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.add('hidden');
        }

        async function saveProfile(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('save-profile-btn');
            const alertEl = document.getElementById('profile-alert');
            const formData = new FormData(form);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            alertEl.classList.add('hidden');

            try {
                const res = await fetch('{{ route("profile.update") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-emerald-950/60 border border-emerald-500/40 text-emerald-300';
                    alertEl.textContent = '✓ ' + (data.message || 'Profile saved.');
                    alertEl.classList.remove('hidden');

                    // Update header avatar & name
                    const headerImg = document.getElementById('header-avatar-img');
                    const headerInitial = document.getElementById('header-avatar-initial');
                    const headerName = document.getElementById('header-user-name');
                    if (headerName && data.user.name) headerName.textContent = data.user.name;

                    if (data.user.avatar_url) {
                        if (headerImg) {
                            headerImg.src = data.user.avatar_url;
                            headerImg.classList.remove('hidden');
                        }
                        if (headerInitial) headerInitial.classList.add('hidden');
                    } else {
                        if (headerImg) {
                            headerImg.src = '';
                            headerImg.classList.add('hidden');
                        }
                        if (headerInitial) {
                            headerInitial.textContent = (data.user.name || 'G').charAt(0).toUpperCase();
                            headerInitial.classList.remove('hidden');
                        }
                    }

                    // Reset removal flag
                    const removeFlag = document.getElementById('remove-avatar-flag');
                    if (removeFlag) removeFlag.value = '0';

                    // If language changed, refresh page to reflect localized strings
                    if (data.user.effective_locale && data.user.effective_locale !== CURRENT_LOCALE) {
                        setTimeout(() => window.location.reload(), 600);
                        return;
                    }

                    setTimeout(() => closeProfileModal(), 1000);
                } else {
                    alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                    alertEl.textContent = data.message || 'Failed to update profile.';
                    alertEl.classList.remove('hidden');
                }
            } catch (err) {
                alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                alertEl.textContent = 'Network or validation error occurred.';
                alertEl.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Profile';
            }
        }

        function detectProfileGps() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            const btnText = document.getElementById('profile-detect-gps-text');
            if (btnText) btnText.textContent = 'Detecting...';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    try {
                        const res = await fetch('{{ route("profile.gps") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': CSRF_TOKEN,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lon })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            const locInput = document.getElementById('profile-input-location');
                            if (locInput) locInput.value = data.location || `${data.city}, ${data.country}`;
                            if (typeof loadRadarData === 'function') loadRadarData();
                        } else {
                            alert(data.message || 'Failed to detect GPS location.');
                        }
                    } catch (err) {
                        alert('Error sending GPS telemetry to server.');
                    } finally {
                        if (btnText) btnText.textContent = 'Detect GPS';
                    }
                },
                (err) => {
                    let msg = 'Failed to detect GPS.';
                    if (err.code === 1) msg = 'Location permission denied in browser.';
                    else if (err.code === 2) msg = 'Position unavailable.';
                    else if (err.code === 3) msg = 'GPS acquisition timed out.';
                    alert(msg);
                    if (btnText) btnText.textContent = 'Detect GPS';
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        // Avatar Upload Handlers
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image exceeds the 5MB file size limit.');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('profile-avatar-preview-img');
                    const initial = document.getElementById('profile-avatar-preview-initial');
                    const removeBtn = document.getElementById('remove-avatar-btn');
                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove('hidden');
                    }
                    if (initial) initial.classList.add('hidden');
                    if (removeBtn) removeBtn.classList.remove('hidden');
                    const removeFlag = document.getElementById('remove-avatar-flag');
                    if (removeFlag) removeFlag.value = '0';
                };
                reader.readAsDataURL(file);
            }
        }

        function markAvatarForRemoval() {
            const fileInput = document.getElementById('avatar-file-input');
            if (fileInput) fileInput.value = '';
            const img = document.getElementById('profile-avatar-preview-img');
            const initial = document.getElementById('profile-avatar-preview-initial');
            const removeBtn = document.getElementById('remove-avatar-btn');
            if (img) {
                img.src = '';
                img.classList.add('hidden');
            }
            if (initial) initial.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.add('hidden');
            const removeFlag = document.getElementById('remove-avatar-flag');
            if (removeFlag) removeFlag.value = '1';
        }

        // Member Details Modal
        async function openMemberCard(member) {
            const modal = document.getElementById('member-card-modal');
            if (!modal) return;

            const cardImg = document.getElementById('card-avatar-img');
            const cardInitial = document.getElementById('card-avatar');
            const bioBox = document.getElementById('card-bio-container');

            document.getElementById('card-name').textContent = member.name || 'Anonymous';

            const ageText = member.age ? `${member.age} yrs` : '—';
            document.getElementById('card-age').textContent = ageText;
            document.getElementById('card-birthday').textContent = member.birthday || '—';
            document.getElementById('card-gender').textContent = GENDER_LABELS[member.gender] || member.gender || '—';
            document.getElementById('card-location').textContent = member.location || (member.city ? `${member.city}, ${member.country}` : '—');

            if (member.bio) {
                document.getElementById('card-bio').textContent = member.bio;
                bioBox.classList.remove('hidden');
            } else {
                bioBox.classList.add('hidden');
            }

            function setCardAvatar(url) {
                if (url) {
                    cardImg.src = url;
                    cardImg.classList.remove('hidden');
                    cardInitial.classList.add('hidden');
                } else {
                    cardImg.src = '';
                    cardImg.classList.add('hidden');
                    cardInitial.textContent = (member.name || 'G').charAt(0).toUpperCase();
                    cardInitial.classList.remove('hidden');
                }
            }

            cardImg.onerror = function() {
                cardImg.classList.add('hidden');
                cardInitial.textContent = (member.name || 'G').charAt(0).toUpperCase();
                cardInitial.classList.remove('hidden');
            };

            // Check if member already has an avatar URL, or if this is the authenticated user, read header avatar
            let currentAvatar = member.avatar_url || null;
            if (!currentAvatar && CURRENT_USER_NAME && member.name === CURRENT_USER_NAME) {
                const headerImg = document.getElementById('header-avatar-img');
                if (headerImg && !headerImg.classList.contains('hidden') && headerImg.src) {
                    currentAvatar = headerImg.src;
                }
            }

            setCardAvatar(currentAvatar);
            modal.classList.remove('hidden');

            // Live fetch to guarantee fresh profile image & details from database
            if (member.name) {
                try {
                    const res = await fetch(`/c/${encodeURIComponent(ROOM_CODE)}/member-profile?name=${encodeURIComponent(member.name)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const live = await res.json();
                        if (live.found) {
                            if (live.avatar_url) {
                                member.avatar_url = live.avatar_url;
                                setCardAvatar(live.avatar_url);
                            }
                            const isPrivileged = live.is_admin || live.is_self;

                            // Age
                            const ageEl = document.getElementById('card-age');
                            if (live.age !== null && live.age !== undefined) {
                                let extra = (isPrivileged && live.privacy?.age_hidden) ? ' <span class="text-[9px] text-amber-300 font-mono">(Private)</span>' : '';
                                ageEl.innerHTML = `${live.age} yrs${extra}`;
                            } else if (live.privacy?.age_hidden) {
                                ageEl.innerHTML = '<span class="text-slate-500 italic text-[11px]">[Classified]</span>';
                            } else {
                                ageEl.textContent = '—';
                            }

                            // Birthday
                            const bdayEl = document.getElementById('card-birthday');
                            if (live.birthday) {
                                let extra = '';
                                if (isPrivileged) {
                                    if (live.privacy?.birthday_hidden) {
                                        extra = ' <span class="text-[9px] text-amber-300 font-mono">(Private)</span>';
                                    } else if (live.privacy?.age_hidden) {
                                        extra = ' <span class="text-[9px] text-sky-300 font-mono">(Year hidden for members)</span>';
                                    }
                                }
                                bdayEl.innerHTML = `${live.birthday}${extra}`;
                            } else if (live.privacy?.birthday_hidden) {
                                bdayEl.innerHTML = '<span class="text-slate-500 italic text-[11px]">[Classified]</span>';
                            } else {
                                bdayEl.textContent = '—';
                            }

                            // Gender
                            document.getElementById('card-gender').textContent = GENDER_LABELS[live.gender] || live.gender || '—';

                            // Location
                            const locEl = document.getElementById('card-location');
                            if (live.location) {
                                let extra = (isPrivileged && live.privacy?.location_hidden) ? ' <span class="text-[9px] text-amber-300 font-mono">(Private)</span>' : '';
                                locEl.innerHTML = `${live.location}${extra}`;
                            } else if (live.privacy?.location_hidden) {
                                locEl.innerHTML = '<span class="text-slate-500 italic text-[11px]">[Classified]</span>';
                            } else {
                                locEl.textContent = '—';
                            }

                            // Bio
                            const bioEl = document.getElementById('card-bio');
                            const bioBadge = document.getElementById('card-bio-private-badge');
                            if (live.bio) {
                                bioEl.textContent = live.bio;
                                bioBox.classList.remove('hidden');
                                if (bioBadge) {
                                    if (isPrivileged && live.privacy?.bio_hidden) {
                                        bioBadge.classList.remove('hidden');
                                    } else {
                                        bioBadge.classList.add('hidden');
                                    }
                                }
                            } else {
                                bioBox.classList.add('hidden');
                            }
                        }
                    }
                } catch (e) {
                    // keep current display
                }
            }
        }

        function closeMemberCard() {
            const modal = document.getElementById('member-card-modal');
            if (modal) modal.classList.add('hidden');
        }

        // Initialize polling loop
        fetchMessages();
        setInterval(fetchMessages, 1500);
    </script>
</body>
</html>
