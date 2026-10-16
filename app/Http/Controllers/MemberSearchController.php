<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BlockService;
use App\Services\UserTagService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSearchController extends Controller
{
    public function __construct(
        protected UserTagService $userTags,
        protected BlockService $blocks
    ) {}

    /**
     * Find members by @tag prefix or display name. Returns only public details.
     * Members blocked either way with the searcher are never returned.
     */
    public function index(Request $request): JsonResponse
    {
        // "%" is stripped so it cannot widen the search; "_" stays because tags use it
        // (as a LIKE wildcard it can only match one extra character).
        $escaped = str_replace('%', '', $this->userTags->normalize((string) $request->query('q', '')));

        if (mb_strlen($escaped) < 2) {
            return response()->json(['members' => []]);
        }

        $hiddenUserIds = $this->blocks->hiddenUserIdsFor($request->user());

        $members = User::query()
            ->when($hiddenUserIds !== [], fn (Builder $builder) => $builder->whereNotIn('id', $hiddenUserIds))
            ->where(fn (Builder $builder) => $builder
                ->where('tag', 'like', "{$escaped}%")
                ->orWhere('name', 'like', "%{$escaped}%"))
            ->orderByRaw('CASE WHEN tag LIKE ? THEN 0 ELSE 1 END', ["{$escaped}%"])
            ->orderBy('tag')
            ->limit(10)
            ->get(['id', 'name', 'tag', 'avatar_path'])
            ->map(fn (User $member): array => [
                'tag' => $member->tag,
                'name' => $member->name,
                'avatar_url' => $member->avatarUrl(),
            ]);

        return response()->json(['members' => $members]);
    }
}
