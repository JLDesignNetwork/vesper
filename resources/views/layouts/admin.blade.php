<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? __('Admin Command Center') }} — {{ __('Vesper') }}</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Leaflet CSS for Global Satellite Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% 0%, #171d2c 0%, #090c15 65%, #05070c 100%);
        }
        .leaflet-container {
            background-color: #090c15 !important;
            font-family: inherit;
        }
        .leaflet-popup-content-wrapper, .leaflet-popup-tip {
            background: #0f172a !important;
            color: #f8fafc !important;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
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
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500/20 selection:text-emerald-300">

    <!-- Executive Header & Menu System -->
    @include('admin.partials.header')

    <!-- Main Content Container -->
    <main class="flex-1 max-w-[1536px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">

        <!-- Flash Status Alerts -->
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between shadow-lg shadow-emerald-950/20 backdrop-blur-md">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
                @if(session('created_room'))
                    <button
                        type="button"
                        onclick="copyChannelLink('{{ session('created_room')['code'] }}', this)"
                        class="px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 font-mono text-[11px] cursor-pointer flex items-center gap-1.5 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        <span>{{ __('Copy Link') }}</span>
                    </button>
                @endif
            </div>
        @endif

        @if(session('error') || (isset($errors) && $errors->any()))
            <div class="p-4 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs space-y-1 shadow-lg shadow-rose-950/20 backdrop-blur-md">
                @if(session('error'))
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @foreach($errors->all() as $error)
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if(session('generated_invite'))
            <div class="p-4 rounded-xl bg-cyan-950/40 border border-cyan-500/30 text-cyan-300 text-xs flex items-center justify-between shadow-lg shadow-cyan-950/20 backdrop-blur-md">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                    <span>{{ __('Channel Invitation Code:') }} <strong class="font-mono text-white text-sm ml-1 select-all">{{ session('generated_invite')['code'] }}</strong></span>
                </div>
                <button
                    type="button"
                    onclick="navigator.clipboard.writeText('{{ session('generated_invite')['url'] }}'); this.innerText = 'Copied!';"
                    class="px-2.5 py-1 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-mono text-[11px] cursor-pointer flex items-center gap-1.5 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <span>{{ __('Copy Invite Link') }}</span>
                </button>
            </div>
        @endif

        <!-- Primary Page Content -->
        @yield('content')

    </main>

    <!-- Dialogs and Modals -->
    @include('admin.modals.create-channel')
    @include('admin.modals.edit-channel')
    @include('admin.modals.invite-channel')
    @include('admin.modals.user-dossier')
    @include('admin.modals.profile')
    @include('admin.modals.disable-2fa')

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Toast Notification Container -->
    <div id="admin-toast" class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-16 opacity-0 pointer-events-none">
        <div class="px-4 py-3 rounded-xl bg-slate-900/95 border border-emerald-500/40 shadow-2xl backdrop-blur-md text-xs font-mono text-emerald-300 flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="admin-toast-msg" class="text-slate-200"></span>
        </div>
    </div>

    <!-- Admin Modular Scripts -->
    @include('admin.scripts.dashboard-scripts')

</body>
</html>
