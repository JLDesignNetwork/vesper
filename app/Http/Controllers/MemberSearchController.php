<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserTagService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSearchController extends Controller
{
    public function __construct(
        protected UserTagService $userTags
    ) {}

    /**
     * Find members by @tag prefix or display name. Returns only public details.
     */
    public function index(Request $request): JsonResponse
    {
        // "%" is stripped so it cannot widen the search; "_" stays because tags use it
        // (as a LIKE wildcard it can only match one extra character).
        $escaped = str_replace('%', '', $this->userTags->normalize((string) $request->query('q', '')));

        if (mb_strlen($escaped) < 2) {
            return response()->json(['members' => []]);
        }

        $members = User::query()
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
