<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * User tags: the unique, searchable @slug every member carries.
 *
 * A tag is 3–30 characters of lowercase letters, digits and underscores, starting with
 * a letter. It is generated from the member's name and can be changed in their profile.
 * A tag mentioned in a channel is highlighted only when that member belongs to the channel.
 */
class UserTagService
{
    public const string PATTERN = '/^[a-z][a-z0-9_]{2,29}$/';

    /** Finds @tag mentions that are not part of an email address or another word. */
    public const string MENTION_PATTERN = '/(?<![A-Za-z0-9_@.])@([A-Za-z][A-Za-z0-9_]{2,29})(?![A-Za-z0-9_])/';

    /**
     * Tags no member may hold, so they cannot impersonate staff or the system.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'admin', 'administrator', 'admins', 'all', 'channel', 'everyone', 'here',
        'mod', 'moderator', 'owner', 'root', 'staff', 'support', 'system', 'vesper',
    ];

    /**
     * Normalize user input to a stored tag: trimmed, lowercased, without a leading @.
     */
    public function normalize(string $input): string
    {
        return strtolower(ltrim(trim($input), '@'));
    }

    /**
     * Generate an unused tag from a display name, e.g. "Ana María" → "ana_maria".
     */
    public function generateFor(string $name, ?int $ignoreUserId = null): string
    {
        $base = (string) Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        if ($base === '' || ! ctype_alpha($base[0])) {
            $base = 'member_'.$base;
        }

        $base = substr(rtrim(str_pad($base, 3, '0'), '_'), 0, 24);

        if (in_array($base, self::RESERVED, true)) {
            $base .= '_1';
        }

        $candidate = $base;
        $suffix = 2;

        while (! $this->isAvailable($candidate, $ignoreUserId)) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }

    /**
     * Whether no other member already holds the tag.
     */
    public function isAvailable(string $tag, ?int $ignoreUserId = null): bool
    {
        return ! User::where('tag', $tag)
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();
    }

    /**
     * Extract the distinct lowercased tags mentioned in the given texts.
     *
     * @param  iterable<?string>  $texts
     * @return list<string>
     */
    public function mentionedTags(iterable $texts): array
    {
        $tags = [];

        foreach ($texts as $text) {
            if (! is_string($text) || $text === '') {
                continue;
            }

            preg_match_all(self::MENTION_PATTERN, $text, $matches);

            foreach ($matches[1] as $tag) {
                $tags[strtolower($tag)] = true;
            }
        }

        return array_keys($tags);
    }

    /**
     * The mentioned tags that belong to the channel's active members or its creator.
     * Only these are highlighted; every other @tag stays plain text, including tags of
     * members in $hiddenUserIds (blocked either way with the viewer).
     *
     * @param  iterable<?string>  $texts
     * @param  list<int>  $hiddenUserIds
     * @return list<string>
     */
    public function memberTagsMentionedIn(Room $room, iterable $texts, array $hiddenUserIds = []): array
    {
        $tags = $this->mentionedTags($texts);

        if ($tags === []) {
            return [];
        }

        $memberIds = DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('status', 'active')
            ->pluck('user_id');

        return User::whereIn('tag', $tags)
            ->where(fn ($query) => $query->whereIn('id', $memberIds)->orWhere('id', $room->created_by_user_id))
            ->when($hiddenUserIds !== [], fn ($query) => $query->whereNotIn('id', $hiddenUserIds))
            ->orderBy('tag')
            ->pluck('tag')
            ->all();
    }
}
