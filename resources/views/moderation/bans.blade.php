<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LanguageService::getDirection(app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Ban Reviews') }} — {{ __('Vesper') }}</title>
    <meta name="theme-color" content="#020617">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% 0%, #171d2c 0%, #090c15 65%, #05070c 100%);
        }
    </style>
</head>
<body class="min-h-full">
    <main class="max-w-4xl mx-auto px-4 py-8 space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-white tracking-tight">{{ __('Ban Reviews') }}</h1>
                <p class="text-xs text-slate-400">{{ __('Admin I and every Admin II vote. A strict majority of the votes cast decides after :hours hours; Admin I breaks ties.', ['hours' => \App\Services\BanVoteService::VOTING_HOURS]) }}</p>
            </div>
            <a href="{{ route('profile.show') }}" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-mono">{{ __('Back to profile') }}</a>
        </header>

        @if(session('status'))
            <div role="status" class="p-3 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-200 text-xs">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-200 text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Open Votes -->
        <section class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-4" aria-labelledby="open-votes-heading">
            <h2 id="open-votes-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Open votes') }} <span class="font-mono text-slate-400">({{ $openCases->count() }})</span></h2>
            @forelse($openCases as $case)
                @php
                    $myBallot = $case->votes->firstWhere('voter_id', $viewer->id);
                    $forBan = $case->votes->where('ban', true)->count();
                    $against = $case->votes->count() - $forBan;
                    $isLift = $case->isLiftVote();
                @endphp
                <article id="ban-case-{{ $case->id }}" class="rounded-xl border {{ $isLift ? 'border-emerald-500/25' : 'border-amber-500/25' }} bg-slate-950/60 p-4 space-y-3 text-xs">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm text-white">
                            @if($isLift)
                                <span class="me-1 px-1.5 py-0.5 rounded bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 font-mono text-[10px] uppercase tracking-wider">{{ __('Lift ban') }}</span>
                            @endif
                            {{ $case->subject?->name }} <span class="font-mono text-emerald-300">{{ '@'.$case->subject?->tag }}</span>
                        </p>
                        <p class="font-mono text-amber-200">{{ __('Closes :when', ['when' => $case->closes_at->diffForHumans()]) }}</p>
                    </div>
                    <p class="text-slate-300 whitespace-pre-line break-words">{{ $case->reason }}</p>
                    <p class="text-slate-500">
                        {{ __('Opened by :name', ['name' => $case->openedBy?->name ?? __('a former admin')]) }}
                        @if($case->report)
                            · {{ __('from a report: :reason', ['reason' => $case->report->reason->label()]) }}
                        @endif
                    </p>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800">
                        <p class="font-mono text-slate-300">
                            @if($isLift)
                                {{ __('Lift ban :for · Keep ban :against', ['for' => $against, 'against' => $forBan]) }}
                            @else
                                {{ __('Ban :for · Don\'t ban :against', ['for' => $forBan, 'against' => $against]) }}
                            @endif
                            @if($myBallot)
                                <span class="text-slate-500">· @if($isLift){{ $myBallot->ban ? __('You voted to keep the ban') : __('You voted to lift the ban') }}@else{{ $myBallot->ban ? __('You voted to ban') : __('You voted not to ban') }}@endif</span>
                            @endif
                        </p>
                        <div class="flex gap-2">
                            @if($isLift)
                                <form method="POST" action="{{ route('moderation.bans.vote', $case) }}">
                                    @csrf
                                    <input type="hidden" name="ban" value="0">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600/80 hover:bg-emerald-500 text-white font-mono cursor-pointer">{{ __('Lift ban') }}</button>
                                </form>
                                <form method="POST" action="{{ route('moderation.bans.vote', $case) }}">
                                    @csrf
                                    <input type="hidden" name="ban" value="1">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-mono cursor-pointer">{{ __('Keep ban') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('moderation.bans.vote', $case) }}">
                                    @csrf
                                    <input type="hidden" name="ban" value="1">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600/80 hover:bg-rose-500 text-white font-mono cursor-pointer">{{ __('Ban') }}</button>
                                </form>
                                <form method="POST" action="{{ route('moderation.bans.vote', $case) }}">
                                    @csrf
                                    <input type="hidden" name="ban" value="0">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-mono cursor-pointer">{{ __('Don\'t ban') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-xs text-slate-500">{{ __('No ban votes are open.') }}</p>
            @endforelse
        </section>

        <!-- Open Reports -->
        <section class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-4" aria-labelledby="open-reports-heading">
            <h2 id="open-reports-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Open reports') }} <span class="font-mono text-slate-400">({{ $openReports->count() }})</span></h2>
            @forelse($openReports as $report)
                <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 space-y-2 text-xs">
                    <p class="text-slate-200">
                        <span class="font-mono text-emerald-300">{{ '@'.$report->reporter?->tag }}</span>
                        {{ __('reported') }}
                        <span class="font-mono text-emerald-300">{{ '@'.$report->subject?->tag }}</span>
                        · {{ $report->reason->label() }}
                        @if($report->origin !== \App\Enums\ReportOrigin::Direct)
                            · <span class="text-amber-200">{{ $report->origin === \App\Enums\ReportOrigin::BlockAttempt ? __('tried to block') : __('tried to remove') }}</span>
                        @endif
                        <span class="text-slate-500">· {{ $report->created_at->diffForHumans() }}</span>
                    </p>
                    @if($report->details)
                        <p class="text-slate-400 whitespace-pre-line break-words">{{ $report->details }}</p>
                    @endif
                    @if($report->subject && ! $report->subject->displaysAsAdmin() && ! $report->subject->isBanned())
                        <form method="POST" action="{{ route('moderation.bans.store') }}" class="flex flex-col sm:flex-row gap-2 pt-1">
                            @csrf
                            <input type="hidden" name="tag" value="{{ $report->subject->tag }}">
                            <input type="hidden" name="report_id" value="{{ $report->id }}">
                            <label for="report-{{ $report->id }}-reason" class="sr-only">{{ __('Reason for the ban vote') }}</label>
                            <input id="report-{{ $report->id }}-reason" name="reason" required maxlength="1000" placeholder="{{ __('Reason for the ban vote') }}" class="flex-1 px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 focus:outline-none focus:border-amber-500/60">
                            <button type="submit" class="px-3 py-2 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-200 font-mono cursor-pointer">{{ __('Open ban vote') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-xs text-slate-500">{{ __('No open reports.') }}</p>
            @endforelse
        </section>

        <!-- Open a vote by tag -->
        <section class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-3" aria-labelledby="open-by-tag-heading">
            <h2 id="open-by-tag-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Open a ban vote') }}</h2>
            <p class="text-[11px] text-slate-400">{{ __('The member becomes read-only at once and sees only that their account is pending an admin review.') }}</p>
            <form method="POST" action="{{ route('moderation.bans.store') }}" class="grid gap-2 text-xs">
                @csrf
                <label for="ban-open-tag" class="text-slate-400">{{ __('Member tag') }}</label>
                <input id="ban-open-tag" name="tag" required maxlength="40" value="{{ old('tag') }}" placeholder="@tag" class="px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 font-mono focus:outline-none focus:border-amber-500/60">
                <label for="ban-open-reason" class="text-slate-400">{{ __('Reason') }}</label>
                <textarea id="ban-open-reason" name="reason" required maxlength="1000" rows="2" class="px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 focus:outline-none focus:border-amber-500/60">{{ old('reason') }}</textarea>
                <button type="submit" class="justify-self-start px-3 py-2 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-200 font-mono cursor-pointer">{{ __('Open ban vote') }}</button>
            </form>
        </section>

        @can('banAdminTwo')
        <!-- Admin I: ban an Admin II directly -->
        <section class="rounded-2xl bg-slate-900/60 border border-rose-500/30 p-5 space-y-3" aria-labelledby="ban-admin-two-heading">
            <h2 id="ban-admin-two-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Ban an Admin II') }}</h2>
            <p class="text-[11px] text-slate-400">{{ __('Admin II members are never put to a vote. This bans them immediately, signs them out and removes their passkeys.') }}</p>
            <form method="POST" action="{{ route('moderation.bans.direct') }}" class="grid gap-2 text-xs">
                @csrf
                <label for="ban-direct-tag" class="text-slate-400">{{ __('Admin II tag') }}</label>
                <input id="ban-direct-tag" name="tag" required maxlength="40" placeholder="@tag" class="px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 font-mono focus:outline-none focus:border-rose-500/60">
                <label for="ban-direct-reason" class="text-slate-400">{{ __('Reason') }}</label>
                <textarea id="ban-direct-reason" name="reason" required maxlength="1000" rows="2" class="px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 focus:outline-none focus:border-rose-500/60"></textarea>
                <label for="ban-direct-confirm" class="flex items-center gap-2 text-slate-300">
                    <input id="ban-direct-confirm" type="checkbox" name="confirm" value="1" required>
                    {{ __('I understand this ban takes effect immediately.') }}
                </label>
                <button type="submit" class="justify-self-start px-3 py-2 rounded-lg bg-rose-600/80 hover:bg-rose-500 text-white font-mono cursor-pointer">{{ __('Ban now') }}</button>
            </form>
        </section>
        @endcan

        <!-- Banned members -->
        <section class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-4" aria-labelledby="banned-members-heading">
            <div class="space-y-1">
                <h2 id="banned-members-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Banned members') }} <span class="font-mono text-slate-400">({{ $bannedMembers->count() }})</span></h2>
                <p class="text-[11px] text-slate-400">{{ __('Lifting a ban takes a vote with the same rules. The member stays banned until the vote decides to lift it.') }}</p>
            </div>
            @php
                $openLiftSubjectIds = $openCases->filter->isLiftVote()->pluck('subject_user_id');
            @endphp
            @forelse($bannedMembers as $member)
                <article id="banned-member-{{ $member->id }}" class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 space-y-2 text-xs">
                    <p class="text-slate-200">
                        {{ $member->name }} <span class="font-mono text-emerald-300">{{ '@'.$member->tag }}</span>
                        <span class="text-slate-500">· {{ __('Banned :when', ['when' => $member->banned_at->diffForHumans()]) }}</span>
                    </p>
                    @if($openLiftSubjectIds->contains($member->id))
                        <p class="text-emerald-200">{{ __('A vote to lift this ban is open.') }}</p>
                    @else
                        <form method="POST" action="{{ route('moderation.bans.lift') }}" class="flex flex-col sm:flex-row gap-2 pt-1">
                            @csrf
                            <input type="hidden" name="tag" value="{{ $member->tag }}">
                            <label for="lift-{{ $member->id }}-reason" class="sr-only">{{ __('Reason for lifting the ban') }}</label>
                            <input id="lift-{{ $member->id }}-reason" name="reason" required maxlength="1000" placeholder="{{ __('Reason for lifting the ban') }}" class="flex-1 px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 focus:outline-none focus:border-emerald-500/60">
                            <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-200 font-mono cursor-pointer">{{ __('Open vote to lift ban') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-xs text-slate-500">{{ __('No members are banned.') }}</p>
            @endforelse
        </section>

        <!-- Recent decisions -->
        <section class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-3" aria-labelledby="recent-decisions-heading">
            <h2 id="recent-decisions-heading" class="text-sm font-semibold text-white tracking-tight">{{ __('Recent decisions') }}</h2>
            @forelse($recentDecisions as $case)
                <p class="text-xs text-slate-300 flex flex-wrap gap-x-2">
                    <span class="font-mono text-emerald-300">{{ '@'.$case->subject?->tag }}</span>
                    <span class="{{ $case->status->leftMemberBanned() ? 'text-rose-300' : 'text-slate-200' }}">{{ $case->status->label() }}</span>
                    <span class="text-slate-500">{{ $case->decision?->label() }} · {{ $case->closed_at?->diffForHumans() }}</span>
                </p>
            @empty
                <p class="text-xs text-slate-500">{{ __('No decisions yet.') }}</p>
            @endforelse
        </section>
    </main>
</body>
</html>
