<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BanCaseStatus;
use App\Enums\ReportStatus;
use App\Models\BanCase;
use App\Models\Report;
use App\Models\User;
use App\Services\BanVoteService;
use App\Services\UserTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The ban review page for Admin I and II: open votes, open reports, and recent decisions.
 */
class BanCaseController extends Controller
{
    public function __construct(
        protected BanVoteService $bans,
        protected UserTagService $userTags
    ) {}

    /**
     * Show open ban votes, open reports and recent decisions.
     */
    public function index(Request $request): View
    {
        $this->bans->closeDue();

        return view('moderation.bans', [
            'viewer' => $request->user(),
            'openCases' => BanCase::open()
                ->with(['subject:id,name,tag', 'openedBy:id,name', 'report', 'votes'])
                ->orderBy('closes_at')
                ->get(),
            'openReports' => Report::where('status', ReportStatus::Open)
                ->with(['reporter:id,name,tag', 'subject:id,name,tag,rank,banned_at'])
                ->latest()
                ->limit(25)
                ->get(),
            'recentDecisions' => BanCase::where('status', '!=', BanCaseStatus::Open)
                ->with(['subject:id,name,tag', 'openedBy:id,name'])
                ->latest('closed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Open a ban vote about a member, optionally from a report about them.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tag' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:1000'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
        ]);

        $subject = $this->memberByTag($validated['tag']);
        $report = isset($validated['report_id']) ? Report::find($validated['report_id']) : null;

        if ($report && $report->subject_user_id !== $subject->id) {
            throw ValidationException::withMessages(['report_id' => __('That report is about a different member.')]);
        }

        $this->bans->open($request->user(), $subject, trim($validated['reason']), $report);

        return redirect()->route('moderation.bans.index')->with('status', __('Ban vote opened for @:tag. They are restricted until it closes.', ['tag' => $subject->tag]));
    }

    /**
     * Cast or change the viewer's ballot on an open case.
     */
    public function vote(Request $request, BanCase $banCase): RedirectResponse
    {
        $validated = $request->validate([
            'ban' => ['required', 'boolean'],
        ]);

        $this->bans->vote($request->user(), $banCase, (bool) $validated['ban']);

        $closed = ! $banCase->fresh()->isOpen();

        return redirect()->route('moderation.bans.index')->with('status', $closed
            ? __('Vote recorded. The case is decided.')
            : __('Vote recorded.'));
    }

    /**
     * Admin I bans an Admin II directly, without a vote.
     */
    public function banAdminTwo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tag' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        $subject = $this->memberByTag($validated['tag']);
        $this->bans->banAdminTwo($request->user(), $subject, trim($validated['reason']));

        return redirect()->route('moderation.bans.index')->with('status', __('@:tag is banned.', ['tag' => $subject->tag]));
    }

    /**
     * Find a member by @tag or fail validation on the tag field.
     */
    protected function memberByTag(string $tag): User
    {
        return User::where('tag', $this->userTags->normalize($tag))->first()
            ?? throw ValidationException::withMessages(['tag' => __('No member has that tag.')]);
    }
}
