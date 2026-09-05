<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Admin Dashboard') }} — {{ __('Sunday City') }}</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Leaflet CSS for Global Map -->
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
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500/20 selection:text-emerald-300">

    <!-- Top Executive Header -->
    <header class="w-full border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-xl sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg shadow-emerald-950/40">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                        {{ __('Sunday City') }}
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono">
                        {{ __('Admin Dashboard') }} • <button type="button" onclick="openProfileModal()" class="hover:text-emerald-400 underline decoration-slate-700 hover:decoration-emerald-400 cursor-pointer transition-colors" title="{{ __('Edit Profile Details') }}"><span id="header-subtitle-name">{{ $adminUser->name }}</span></button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 font-mono text-xs">
                <!-- Profile Settings Trigger -->
                <button
                    type="button"
                    onclick="openProfileModal()"
                    class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs flex items-center gap-2 transition-colors cursor-pointer"
                    title="{{ __('Edit Profile Details') }}"
                >
                    <div class="w-5 h-5 rounded-full overflow-hidden bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold text-[10px] shrink-0">
                        @if($adminUser->avatar_path)
                            <img id="header-avatar-img" src="{{ $adminUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                            <span id="header-avatar-initial" class="hidden">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                        @else
                            <img id="header-avatar-img" src="" class="w-full h-full object-cover hidden" alt="">
                            <span id="header-avatar-initial">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <span id="header-user-name" class="font-sans font-medium text-xs max-w-[110px] truncate">{{ $adminUser->name }}</span>
                    <span class="text-[10px] text-emerald-400 font-mono hidden sm:inline">{{ __('Profile') }}</span>
                </button>

                <!-- Create Channel Button -->
                <button
                    type="button"
                    onclick="openCreateModal()"
                    class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-sans font-medium text-xs shadow-lg shadow-emerald-950/40 hover:shadow-emerald-900/40 transition-all flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>{{ __('New Channel') }}</span>
                </button>

                <!-- Logout Form -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-rose-950/40 text-slate-400 hover:text-rose-400 border border-slate-800 hover:border-rose-500/30 transition-colors cursor-pointer"
                        title="{{ __('Logout') }}"
                    >
                        {{ __('Logout') }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-8 space-y-8">

        <!-- Flash Status Alerts -->
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between shadow-lg">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
                @if(session('created_room'))
                    <button
                        type="button"
                        onclick="copyChannelLink('{{ session('created_room')['code'] }}', this)"
                        class="px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 font-mono text-[11px] cursor-pointer flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        <span>{{ __('Copy Link') }}</span>
                    </button>
                @endif
            </div>
        @endif

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Active Channels -->
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md relative overflow-hidden">
                <div class="flex items-center justify-between text-slate-400 text-xs font-mono mb-2">
                    <span>{{ __('Active Channels') }}</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ $activeRooms }} <span class="text-xs text-slate-500 font-normal">/ {{ $totalRooms }}</span>
                </div>
            </div>

            <!-- Registered Operatives -->
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md">
                <div class="text-slate-400 text-xs font-mono mb-2">{{ __('Registered Users') }}</div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ number_format($totalUsers) }}
                </div>
            </div>

            <!-- Total Messages -->
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md">
                <div class="text-slate-400 text-xs font-mono mb-2">{{ __('Total Transmissions') }}</div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ number_format($totalMessages) }}
                </div>
            </div>

            <!-- Tracked Visitors -->
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md">
                <div class="text-slate-400 text-xs font-mono mb-2">{{ __('Visitors Tracked') }}</div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ number_format($totalVisitors) }}
                </div>
            </div>

            <!-- Storage Used -->
            <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md">
                <div class="text-slate-400 text-xs font-mono mb-2">{{ __('Storage Used') }}</div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ $formattedStorage }}
                </div>
            </div>
        </div>

        <!-- Channels Section -->
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
            <div class="p-5 border-b border-slate-800/80 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Channels Directory') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __('Discreet, encrypted private messaging platform') }}</p>
                </div>
                <button
                    type="button"
                    onclick="openCreateModal()"
                    class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 text-xs font-mono flex items-center gap-1.5 cursor-pointer transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>{{ __('Create Channel') }}</span>
                </button>
            </div>

            <!-- Table of Channels -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/40">
                            <th class="py-3 px-4">{{ __('Channel') }}</th>
                            <th class="py-3 px-4">{{ __('PIN') }}</th>
                            <th class="py-3 px-4">{{ __('Translations') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Notifications') }}</th>
                            <th class="py-3 px-4">{{ __('Messages') }}</th>
                            <th class="py-3 px-4">{{ __('Direct Invite Link') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-sans">
                        @forelse($rooms as $room)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-white flex items-center gap-2">
                                        <span>{{ $room->title ?: $room->code }}</span>
                                        @if($room->burn_after_reading)
                                            <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-300">
                                                BURN
                                            </span>
                                        @endif
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-400 mt-0.5">
                                        {{ $room->code }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-emerald-400 font-bold tracking-wider text-xs">
                                            {{ $room->pin ?: '••••••' }}
                                        </span>
                                        @if($room->pin)
                                            <button
                                                type="button"
                                                onclick="copyText('{{ $room->pin }}', this, '{{ __('PIN copied!') }}')"
                                                title="{{ __('Copy PIN') }}"
                                                class="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-emerald-300 transition-colors cursor-pointer"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        @foreach($room->effectiveAllowedLanguages() as $lang)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 text-[10px] uppercase font-bold">
                                                {{ strtoupper($lang) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    @if($room->status === 'active')
                                        <span class="inline-flex items-center gap-1 text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 text-[10px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700 text-[10px]">
                                            {{ __('Archived') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <form method="POST" action="{{ route('admin.channels.toggle-notifications', ['id' => $room->id]) }}" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            title="{{ $room->notify_admin ? __('Admin alerts enabled. Click to disable.') : __('Admin alerts disabled. Click to enable.') }}"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-sans transition-colors cursor-pointer {{ $room->notify_admin ? 'text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 hover:bg-emerald-500/20' : 'text-slate-400 bg-slate-900 border border-slate-800 hover:text-slate-200 hover:border-slate-700' }}"
                                        >
                                            <svg class="w-3.5 h-3.5 {{ $room->notify_admin ? 'text-emerald-400' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span>{{ $room->notify_admin ? __('Enabled') : __('Disabled') }}</span>
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-300">
                                    {{ $room->messages_count }}
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onclick="copyChannelLink('{{ $room->code }}', this)"
                                            class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-emerald-500/40 text-slate-300 hover:text-emerald-300 transition-colors flex items-center gap-1.5 cursor-pointer text-[11px]"
                                            title="{{ __('Copy channel invitation link') }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                            <span>{{ __('Copy Link') }}</span>
                                        </button>
                                        @if($room->pin)
                                            <button
                                                type="button"
                                                onclick="copyFullInvite('{{ $room->code }}', '{{ addslashes($room->title ?: $room->code) }}', '{{ $room->pin }}', this)"
                                                class="px-2 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 transition-colors text-[10px] cursor-pointer"
                                                title="{{ __('Copy Link + PIN package') }}"
                                            >
                                                + {{ __('PIN') }}
                                            </button>
                                        @endif
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-500 mt-1 truncate max-w-[180px]" title="/c/{{ $room->code }}">
                                        /c/{{ $room->code }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5 font-mono">
                                        <button
                                            type="button"
                                            onclick="openEditModal({
                                                id: '{{ $room->id }}',
                                                code: '{{ $room->code }}',
                                                title: '{{ addslashes($room->title ?? '') }}',
                                                pin: '{{ addslashes($room->pin ?? '') }}',
                                                status: '{{ $room->status }}',
                                                notify_admin: {{ $room->notify_admin ? 'true' : 'false' }},
                                                languages: {{ json_encode($room->effectiveAllowedLanguages()) }}
                                            })"
                                            class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors text-[11px] cursor-pointer"
                                        >
                                            {{ __('Edit') }}
                                        </button>

                                        <a
                                            href="{{ route('rooms.show', ['room' => $room->code]) }}"
                                            class="px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition-colors text-[11px]"
                                        >
                                            Enter
                                        </a>

                                        <form method="POST" action="{{ route('admin.channels.toggle', ['id' => $room->id]) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors text-[11px] cursor-pointer"
                                            >
                                                {{ $room->status === 'active' ? 'Archive' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.channels.destroy', ['id' => $room->id]) }}" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to permanently erase this channel and all its data?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 transition-colors text-[11px] cursor-pointer"
                                            >
                                                {{ __('Purge') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-500 font-mono">
                                    No channels exist yet. Click "Create Channel" to create your first secure channel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Registered Users Section -->
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
            <div class="p-5 border-b border-slate-800/80 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Registered Users') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __('Discreet database of all registered accounts and member profiles') }}</p>
                </div>
                <div class="px-3 py-1 rounded-xl bg-slate-800/80 border border-slate-700/80 font-mono text-xs text-slate-300">
                    {{ $registeredUsers->count() }} {{ __('Total') }}
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
                                <td class="py-3.5 px-4 font-mono text-slate-300">
                                    {{ $regUser->gender ? ucfirst($regUser->gender) : '—' }}
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

        <!-- Global Traffic Map Section -->
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
            <div class="p-5 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white tracking-tight flex items-center gap-2">
                        <span>{{ __('Global Traffic Map') }}</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __('Real-time geospatial distribution of operative nodes and channel traffic.') }}</p>
                </div>
                <div class="flex items-center gap-2 font-mono text-xs">
                    <button
                        type="button"
                        id="admin-gps-sync-btn"
                        onclick="syncAdminGps()"
                        class="px-3 py-1.5 rounded-xl bg-cyan-950/50 hover:bg-cyan-900/50 border border-cyan-500/40 text-cyan-300 transition-all flex items-center gap-2 cursor-pointer shadow-[0_0_15px_rgba(6,182,212,0.15)]"
                        title="{{ __('Synchronize high-precision GPS coordinates from your browser') }}"
                    >
                        <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span id="admin-gps-btn-text">{{ __('SYNC GPS') }}</span>
                    </button>
                </div>
            </div>
            <div id="admin-map" class="h-96 w-full"></div>
        </div>

        <!-- Recent Visitors & Intelligence Section -->
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md overflow-hidden">
            <div class="p-5 border-b border-slate-800/80">
                <h3 class="text-base font-semibold text-white tracking-tight">{{ __('Recent Visitors & Intelligence') }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">Audited access telemetry and visitor network details.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 font-mono text-[11px] uppercase bg-slate-950/40">
                            <th class="py-3 px-4">{{ __('Visitor') }}</th>
                            <th class="py-3 px-4">{{ __('Location') }}</th>
                            <th class="py-3 px-4">{{ __('IP Address') }}</th>
                            <th class="py-3 px-4">{{ __('Channel') }}</th>
                            <th class="py-3 px-4">{{ __('Last Active') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-sans">
                        @forelse($recentVisitors as $v)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-4 font-mono text-slate-200">
                                    {{ $v['alias'] }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="mr-1.5">{{ $v['flag'] }}</span>
                                    <span class="text-slate-300">{{ $v['city'] }}, {{ $v['country'] }}</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">
                                    {{ $v['ip_address'] }}
                                </td>
                                <td class="py-3 px-4 font-mono text-emerald-400">
                                    {{ $v['room_code'] }}
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">
                                    {{ $v['last_seen_human'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500 font-mono">
                                    {{ __('No visitors recorded yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Create Channel Modal -->
    <div id="create-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
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

    <!-- User Dossier Modal -->
    <div id="user-dossier-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-5 font-sans">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
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
                    <div id="dossier-gender" class="text-slate-200 mt-1"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Location') }}</div>
                    <div id="dossier-location" class="text-slate-200 mt-1"></div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Email Alerts') }}</div>
                    <div id="dossier-alerts" class="text-slate-200 mt-1 font-semibold"></div>
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
                    <div class="text-[10px] text-slate-500 uppercase">{{ __('Bio / Intelligence Dossier') }}</div>
                    <span id="dossier-bio-badge" class="hidden text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20">🔒 {{ __('Hidden from members') }}</span>
                </div>
                <p id="dossier-bio" class="text-xs text-slate-300 leading-relaxed italic whitespace-pre-wrap"></p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 font-mono text-xs">
                <button
                    type="button"
                    onclick="closeUserDossier()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
                >
                    {{ __('Close Dossier') }}
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

    <!-- Edit Channel Modal -->
    <div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
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

    <!-- Member / User Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4 font-sans">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold font-mono text-sm">
                        {{ strtoupper(substr($adminUser->name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">{{ __('Profile Settings') }}</h3>
                    </div>
                </div>
                <button type="button" onclick="closeProfileModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <form id="profile-form" onsubmit="saveProfile(event)" class="space-y-3.5 text-xs font-sans">
                @csrf

                <div id="profile-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>

                <!-- Custom Avatar Uploader -->
                <div class="flex items-center gap-3.5 p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                    <div class="relative group shrink-0">
                        <div id="profile-avatar-preview-wrap" class="w-14 h-14 rounded-2xl overflow-hidden bg-emerald-500/20 border-2 border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold font-mono text-lg">
                            @if($adminUser->avatar_path)
                                <img id="profile-avatar-preview-img" src="{{ $adminUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                <span id="profile-avatar-preview-initial" class="hidden">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                            @else
                                <img id="profile-avatar-preview-img" src="" class="w-full h-full object-cover hidden" alt="">
                                <span id="profile-avatar-preview-initial">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
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
                                class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium cursor-pointer transition-colors {{ $adminUser->avatar_path ? '' : 'hidden' }}"
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
                        id="profile-input-name"
                        required
                        value="{{ $adminUser->name }}"
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1">{{ __('Email Address') }} <span class="text-rose-400">*</span></label>
                    <input
                        type="email"
                        name="email"
                        id="profile-input-email"
                        required
                        value="{{ $adminUser->email }}"
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Birthday') }}</label>
                        <input
                            type="date"
                            name="birthday"
                            id="profile-input-birthday"
                            value="{{ $adminUser->birthday?->format('Y-m-d') }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:outline-none focus:border-emerald-500"
                        >
                    </div>
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Gender') }}</label>
                        <select
                            name="gender"
                            id="profile-input-gender"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                        >
                            <option value="">{{ __('Prefer not to say') }}</option>
                            <option value="Male" {{ $adminUser->gender === 'Male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                            <option value="Female" {{ $adminUser->gender === 'Female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                            <option value="Non-binary" {{ $adminUser->gender === 'Non-binary' ? 'selected' : '' }}>{{ __('Non-binary') }}</option>
                            <option value="Other" {{ $adminUser->gender === 'Other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="font-medium text-slate-300">{{ __('Location') }}</label>
                        <button
                            type="button"
                            id="profile-detect-gps-btn"
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
                        value="{{ $adminUser->location }}"
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1">{{ __('Bio / Status') }}</label>
                    <textarea
                        name="bio"
                        id="profile-input-bio"
                        rows="2"
                        placeholder="A brief note about yourself..."
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                    >{{ $adminUser->bio }}</textarea>
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
                        {{ __('Choose which details are concealed when regular members inspect your profile in chat.') }}
                    </p>
                    <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-xs">
                        <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                name="hide_age"
                                value="1"
                                {{ $adminUser->hide_age ? 'checked' : '' }}
                                class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                            >
                            <span class="text-slate-300 text-[11px]">{{ __('Hide Age') }}</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                name="hide_birthday"
                                value="1"
                                {{ $adminUser->hide_birthday ? 'checked' : '' }}
                                class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                            >
                            <span class="text-slate-300 text-[11px]">{{ __('Hide Birthday') }}</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                name="hide_location"
                                value="1"
                                {{ $adminUser->hide_location ? 'checked' : '' }}
                                class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                            >
                            <span class="text-slate-300 text-[11px]">{{ __('Hide Location') }}</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                name="hide_bio"
                                value="1"
                                {{ $adminUser->hide_bio ? 'checked' : '' }}
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
                        id="profile-input-password"
                        placeholder="••••••••"
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                    >
                </div>

                <div class="pt-1">
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800 hover:border-emerald-500/40 cursor-pointer transition-colors select-none">
                        <input
                            type="checkbox"
                            name="email_notifications"
                            id="profile-input-email-notifications"
                            value="1"
                            {{ $adminUser->email_notifications ? 'checked' : '' }}
                            class="mt-0.5 rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                        >
                        <div class="text-xs">
                            <span class="font-medium text-white block">{{ __('Email Notifications') }}</span>
                            <span class="text-slate-400 text-[11px] block mt-0.5 leading-snug">{{ __('Receive email notifications for critical network transmissions and alerts.') }}</span>
                        </div>
                    </label>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 font-mono">
                    <button
                        type="button"
                        onclick="closeProfileModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        id="save-profile-btn"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer"
                    >
                        {{ __('Save Profile') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        function openCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            generateChannelCode();
            generatePasscode();
        }

        function closeCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
        }

        function openEditModal(room) {
            document.getElementById('edit-channel-form').action = `/admin/channels/${room.id}`;
            document.getElementById('edit-input-code').value = room.code;
            document.getElementById('edit-input-title').value = room.title || '';
            document.getElementById('edit-input-pin').value = room.pin || '';
            document.getElementById('edit-input-status').value = room.status || 'active';

            const langs = room.languages || ['en', 'ru', 'fr', 'it'];
            ['en', 'ru', 'fr', 'it'].forEach(code => {
                const el = document.getElementById(`edit-lang-${code}`);
                if (el) {
                    el.checked = langs.includes(code);
                }
            });

            const notifyEl = document.getElementById('edit-notify-admin');
            if (notifyEl) {
                notifyEl.checked = !!room.notify_admin;
            }

            document.getElementById('edit-modal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
        }

        function openUserDossier(user) {
            const avatarUrl = user.avatar_url || (user.avatar_path ? ('/storage/' + user.avatar_path) : null);
            const dossierImg = document.getElementById('dossier-avatar-img');
            const dossierInitial = document.getElementById('dossier-avatar');
            if (avatarUrl) {
                if (dossierImg) {
                    dossierImg.src = avatarUrl;
                    dossierImg.classList.remove('hidden');
                }
                if (dossierInitial) dossierInitial.classList.add('hidden');
            } else {
                if (dossierImg) {
                    dossierImg.src = '';
                    dossierImg.classList.add('hidden');
                }
                if (dossierInitial) {
                    dossierInitial.textContent = (user.name || 'U').substring(0, 2).toUpperCase();
                    dossierInitial.classList.remove('hidden');
                }
            }

            document.getElementById('dossier-name').textContent = user.name || 'Operative';
            document.getElementById('dossier-email').textContent = user.email || '—';

            // Latest IP
            const ipEl = document.getElementById('dossier-ip-val');
            if (ipEl) {
                ipEl.textContent = user.latest_ip || '—';
            }

            // Age & Birthday
            let ageText = '—';
            if (user.birthday) {
                const bday = new Date(user.birthday);
                const ageYears = Math.floor((new Date() - bday) / (365.25 * 24 * 60 * 60 * 1000));
                ageText = `${ageYears} yrs (${user.birthday.substring(0, 10)})`;
            }
            const ageEl = document.getElementById('dossier-age');
            if (ageEl) {
                let badges = '';
                if (user.hide_age) badges += ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 Age Hidden</span>';
                if (user.hide_birthday) badges += ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 Bday Hidden</span>';
                ageEl.innerHTML = `<span>${ageText}</span>${badges}`;
            }

            document.getElementById('dossier-gender').textContent = user.gender ? (user.gender.charAt(0).toUpperCase() + user.gender.slice(1)) : '—';

            // Location
            const locEl = document.getElementById('dossier-location');
            if (locEl) {
                let locBadge = user.hide_location ? ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 Hidden</span>' : '';
                locEl.innerHTML = `<span>${user.location || '—'}</span>${locBadge}`;
            }

            document.getElementById('dossier-alerts').textContent = user.email_notifications ? 'Enabled' : 'Disabled';
            document.getElementById('dossier-joined').textContent = user.created_at ? new Date(user.created_at).toLocaleString() : '—';

            // Bio
            const bioEl = document.getElementById('dossier-bio');
            const bioBadgeEl = document.getElementById('dossier-bio-badge');
            if (bioEl) {
                bioEl.textContent = user.bio || 'No intelligence notes or biography recorded.';
            }
            if (bioBadgeEl) {
                if (user.hide_bio) {
                    bioBadgeEl.classList.remove('hidden');
                } else {
                    bioBadgeEl.classList.add('hidden');
                }
            }

            // Privacy summary
            const privacyEl = document.getElementById('dossier-privacy-summary');
            if (privacyEl) {
                const hiddenItems = [];
                if (user.hide_age) hiddenItems.push('Age');
                if (user.hide_birthday) hiddenItems.push('Birthday');
                if (user.hide_location) hiddenItems.push('Location');
                if (user.hide_bio) hiddenItems.push('Bio');

                if (hiddenItems.length > 0) {
                    privacyEl.innerHTML = `<span class="text-amber-400 font-mono">🔒 Concealed from members: <strong>${hiddenItems.join(', ')}</strong> (Admin overrides and sees all)</span>`;
                } else {
                    privacyEl.innerHTML = `<span class="text-emerald-400 font-mono">✓ All profile fields visible to members</span>`;
                }
            }

            const editMyProfileBtn = document.getElementById('dossier-edit-my-profile-btn');
            if (editMyProfileBtn) {
                if (user.id === {{ Auth::id() }}) {
                    editMyProfileBtn.classList.remove('hidden');
                } else {
                    editMyProfileBtn.classList.add('hidden');
                }
            }

            document.getElementById('user-dossier-modal').classList.remove('hidden');
        }

        function copyDossierIp() {
            const ip = document.getElementById('dossier-ip-val')?.textContent?.trim();
            if (ip && ip !== '—') {
                copyText(ip, document.getElementById('dossier-copy-ip-btn'), '{{ __("IP copied!") }}');
            }
        }

        function closeUserDossier() {
            document.getElementById('user-dossier-modal').classList.add('hidden');
        }

        // Profile Modal Handlers
        function openProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.add('hidden');
        }

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    showToast('{{ __("Image exceeds the 5MB file size limit.") }}');
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

        async function saveProfile(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('save-profile-btn');
            const alertEl = document.getElementById('profile-alert');
            const formData = new FormData(form);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            alertEl.classList.add('hidden');

            try {
                const res = await fetch('{{ route("profile.update") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
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
                    const headerSubName = document.getElementById('header-subtitle-name');
                    if (headerName && data.user.name) headerName.textContent = data.user.name;
                    if (headerSubName && data.user.name) headerSubName.textContent = data.user.name;

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

                    showToast('{{ __("Profile updated successfully!") }}');
                    setTimeout(() => {
                        closeProfileModal();
                        window.location.reload();
                    }, 800);
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
                showToast('{{ __("Geolocation is not supported by your browser.") }}');
                return;
            }

            const btnText = document.getElementById('profile-detect-gps-text');
            if (btnText) btnText.textContent = '{{ __("Detecting...") }}';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const res = await fetch('{{ route("profile.gps") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lon })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            const locInput = document.getElementById('profile-input-location');
                            if (locInput) locInput.value = data.location || `${data.city}, ${data.country}`;
                            showToast(`✓ GPS Detected: ${data.city || ''}, ${data.country || ''}`);
                        } else {
                            showToast(data.message || 'Failed to detect GPS location.');
                        }
                    } catch (err) {
                        showToast('Error sending GPS data.');
                    } finally {
                        if (btnText) btnText.textContent = '{{ __("Detect GPS") }}';
                    }
                },
                (err) => {
                    let msg = 'Failed to detect GPS.';
                    if (err.code === 1) msg = 'Location permission denied in browser.';
                    else if (err.code === 2) msg = 'Position unavailable.';
                    else if (err.code === 3) msg = 'GPS acquisition timed out.';
                    showToast(msg);
                    if (btnText) btnText.textContent = '{{ __("Detect GPS") }}';
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        function generateChannelCode() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            let prefix = '';
            for (let i = 0; i < 4; i++) {
                prefix += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            const num = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('input-code').value = `${prefix}-${num}`;
        }

        function generatePasscode() {
            const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            let pin = '';
            for (let i = 0; i < 8; i++) {
                pin += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('input-passcode').value = pin;
        }

        // Global Map Instance Reference
        let adminMapInstance = null;
        let adminUserMarker = null;

        // Initialize Global Leaflet Map
        document.addEventListener('DOMContentLoaded', () => {
            const mapEl = document.getElementById('admin-map');
            if (!mapEl) return;

            adminMapInstance = L.map('admin-map', {
                zoomControl: true,
                attributionControl: false
            }).setView([25, 0], 2);

            const cartoKey = @json(config('services.carto.key'));
            const tileUrl = 'https://{s}.basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png' + (cartoKey ? '?key=' + encodeURIComponent(cartoKey) : '');

            L.tileLayer(tileUrl, {
                maxZoom: 19,
                subdomains: 'abcd',
            }).addTo(adminMapInstance);

            const markers = @json($mapMarkers);

            markers.forEach(m => {
                if (m.latitude && m.longitude) {
                    const isUser = !!m.is_user;
                    const isAdmin = m.role === 'admin';
                    const color = isAdmin ? '#a855f7' : (isUser ? '#06b6d4' : '#10b981');

                    const circle = L.circleMarker([m.latitude, m.longitude], {
                        color: color,
                        fillColor: color,
                        fillOpacity: isUser ? 0.85 : 0.6,
                        radius: isUser ? 8 : 6,
                        weight: isUser ? 3 : 2
                    }).addTo(adminMapInstance);

                    const avatarHtml = m.avatar_url 
                        ? `<img src="${m.avatar_url}" class="w-5 h-5 rounded-full object-cover border border-slate-600 inline-block mr-1.5" alt="">`
                        : `<span class="w-5 h-5 rounded-full bg-slate-800 border border-slate-700 text-slate-300 font-bold text-[9px] inline-flex items-center justify-center mr-1.5">${(m.alias || 'U').substring(0, 1).toUpperCase()}</span>`;

                    const roleBadge = isUser
                        ? `<span class="text-[9px] font-mono px-1.5 py-0.5 rounded ${isAdmin ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'}">${isAdmin ? 'COMMAND' : 'MEMBER'}</span>`
                        : `<span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">VISITOR</span>`;

                    circle.bindPopup(`
                        <div class="text-xs font-mono p-1 space-y-1">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-800 pb-1 mb-1">
                                <div class="flex items-center font-bold text-white">
                                    ${avatarHtml}
                                    <span class="truncate max-w-[120px]">${m.alias}</span>
                                </div>
                                ${roleBadge}
                            </div>
                            <div class="text-slate-300 font-medium flex items-center gap-1">
                                <span>${m.flag || '📍'}</span>
                                <span>${m.city || ''}, ${m.country || ''}</span>
                            </div>
                            <div class="text-[10px] text-slate-400">GPS: ${Number(m.latitude).toFixed(4)}, ${Number(m.longitude).toFixed(4)}</div>
                            ${m.room_code ? `<div class="text-emerald-400 text-[11px]">Channel: ${m.room_code}</div>` : ''}
                            <div class="text-slate-500 text-[10px]">${m.last_seen_human || 'Active'}</div>
                        </div>
                    `);
                }
            });
        });

        // Admin GPS Synchronization
        function syncAdminGps() {
            if (!navigator.geolocation) {
                showToast('{{ __("Geolocation is not supported by your browser.") }}');
                return;
            }

            const btn = document.getElementById('admin-gps-sync-btn');
            const btnText = document.getElementById('admin-gps-btn-text');
            if (btn) btn.disabled = true;
            if (btnText) btnText.textContent = '{{ __("LOCATING...") }}';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const res = await fetch('{{ route("admin.gps") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lon })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            showToast(`✓ GPS Synced: ${data.city || 'Verified Location'}, ${data.country || ''}`);

                            if (adminMapInstance) {
                                adminMapInstance.flyTo([lat, lon], 12, { animate: true, duration: 1.5 });

                                if (adminUserMarker) {
                                    adminUserMarker.setLatLng([lat, lon]);
                                } else {
                                    adminUserMarker = L.circleMarker([lat, lon], {
                                        color: '#a855f7',
                                        fillColor: '#a855f7',
                                        fillOpacity: 0.9,
                                        radius: 10,
                                        weight: 3
                                    }).addTo(adminMapInstance);
                                }

                                adminUserMarker.bindPopup(`
                                    <div class="text-xs font-mono p-1">
                                        <div class="font-bold text-purple-300 flex items-center gap-1">
                                            <span>${data.flag || '📍'}</span>
                                            <span>${data.city || 'Command Center'}, ${data.country || 'HQ'}</span>
                                        </div>
                                        <div class="text-slate-400 mt-0.5">GPS: ${lat.toFixed(4)}, ${lon.toFixed(4)}</div>
                                        <div class="text-purple-400 text-[10px] mt-0.5">Admin Live Position</div>
                                    </div>
                                `).openPopup();
                            }

                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showToast(data.message || 'Failed to sync GPS coordinates.');
                        }
                    } catch (err) {
                        showToast('Error sending GPS telemetry to server.');
                    } finally {
                        if (btn) btn.disabled = false;
                        if (btnText) btnText.textContent = '{{ __("SYNC GPS") }}';
                    }
                },
                (err) => {
                    let msg = 'Failed to obtain GPS coordinates.';
                    if (err.code === 1) msg = 'Location access was denied in browser permissions.';
                    else if (err.code === 2) msg = 'Position unavailable.';
                    else if (err.code === 3) msg = 'Location request timed out.';
                    showToast(msg);
                    if (btn) btn.disabled = false;
                    if (btnText) btnText.textContent = '{{ __("SYNC GPS") }}';
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        function showToast(msg) {
            const toast = document.getElementById('admin-toast');
            const toastMsg = document.getElementById('admin-toast-msg');
            if (!toast || !toastMsg) return;
            toastMsg.textContent = msg;
            toast.classList.remove('translate-y-16', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                toast.classList.add('translate-y-16', 'opacity-0', 'pointer-events-none');
            }, 3000);
        }

        function fallbackCopy(text, callback) {
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
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (successful) {
                    if (callback) callback();
                } else {
                    prompt('Copy to clipboard (Ctrl+C / Cmd+C):', text);
                }
            } catch (e) {
                prompt('Copy to clipboard (Ctrl+C / Cmd+C):', text);
            }
        }

        function copyText(text, btnElement = null, successMsg = '{{ __("Copied to clipboard!") }}') {
            function onCopied() {
                showToast(successMsg);
                if (btnElement) {
                    const originalHtml = btnElement.innerHTML;
                    btnElement.innerHTML = `
                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-emerald-400 font-semibold">{{ __('Copied!') }}</span>
                    `;
                    btnElement.classList.add('border-emerald-500/50', 'bg-emerald-950/40');
                    setTimeout(() => {
                        btnElement.innerHTML = originalHtml;
                        btnElement.classList.remove('border-emerald-500/50', 'bg-emerald-950/40');
                    }, 2000);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text)
                    .then(onCopied)
                    .catch(() => fallbackCopy(text, onCopied));
            } else {
                fallbackCopy(text, onCopied);
            }
        }

        function copyChannelLink(roomCode, btnElement = null) {
            const url = window.location.origin + `/c/${encodeURIComponent(roomCode)}`;
            copyText(url, btnElement, `{{ __('Channel link copied!') }} (${url})`);
        }

        function copyFullInvite(roomCode, roomTitle, pin, btnElement = null) {
            const url = window.location.origin + `/c/${encodeURIComponent(roomCode)}`;
            const packageText = `Channel: ${roomTitle}\nLink: ${url}\nPIN: ${pin}`;
            copyText(packageText, btnElement, `{{ __('Link & PIN package copied!') }}`);
        }
    </script>

    <!-- Toast Notification Container -->
    <div id="admin-toast" class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-16 opacity-0 pointer-events-none">
        <div class="px-4 py-3 rounded-xl bg-slate-900/95 border border-emerald-500/40 shadow-2xl backdrop-blur-md text-xs font-mono text-emerald-300 flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="admin-toast-msg" class="text-slate-200"></span>
        </div>
    </div>
</body>
</html>
