---
title: Vesper Channels Refactor Plan
type: web-app
project: vesper
generation: "2610"
plan_version: 3
status: approved
protocol: document-first
gvs_baseline: 2609.11.0-bs
created_at: 2026-10-16T12:25:00+02:00
artifact: https://claude.ai/code/artifact/7b6d615a-665f-4a60-a5eb-80ad3aaff9b5
lifecycle: persistent
cleanup: manual
---

# Vesper Channels Refactor Plan

Turn Vesper from an admin-issued, code-and-PIN channel system into a member-run platform in
the style of Telegram or Discord. This file is the in-repo copy of the reviewed plan (v3).
Backlog items live in [`backlog.json`](backlog.json).

## Spec corrections assumed

| Spec says | Read as |
| --- | --- |
| `prf` listed as "public free-entry" | Private Free-Entry, as its description says |
| Second "Admin II" heading under Admin Panel | Admin III (it describes a view of the user's own channels) |
| User I: "less than 5 but more than 2" channels | Exactly 3 or 4 qualifying channels |

## Owner decisions

| # | Decision |
| --- | --- |
| Q1 | Admin I and II see every channel's metadata and members. Reading a private channel requires membership, except Admin I, whose override is audited. |
| Q2 | A channel qualifies when active, unexpired, with a message in the last **10 days** and 10+ verified-email members. Recompute nightly and on membership change; demotion is immediate. |
| Q3 | Block/Kick against Admin III shows normally but files a report. Rank stays hidden. |
| Q4 / A1 | TOTP 2FA keeps PIN-free entry to `prs`, `pus`, `pra-2` (never `pra`), and can replace the recovery email in the biometric precondition. |
| Q5 | Recovery email counts as the second verified email. Phone verification deferred (`AUTH-IDEA-01`). |
| Q6 | Voters: Admin I + all Admin II. 72h window, strict majority of cast ballots, Admin I breaks ties and decides alone when no Admin II exists. Subject muted; vote visible in every Admin I/II panel. |
| Q7 / A2 | Guest mode retired. Guest test cases move to signed-in users; `GuestRestrictionsTest` proves guests can only browse the public directory. |
| Q8 | One seeded, undeletable `pra` and `pra-2`. Only Admin I creates more. |
| Q9 | Admin II and III views hide geolocation, IPs, and access-log locations. Admin III sees only their own channels. |
| Q10 | Secured clearance clears on route exit, `pagehide`, or 15 minutes idle, enforced server-side. |
| D1 | Only the channel creator and Admin I may purge (destroy) a channel (`CHAN-ISSUE-02`). |
| D2 | The in-channel radar (IPs, ISP, GPS) is Admin I only (`CHAN-ISSUE-03`). |
| A3 | Ban-vote subject sees "Your account is restricted pending an admin review", no details. |

## Audit pass 1 findings (applied)

| ID | Severity | Finding | Resolution |
| --- | --- | --- | --- |
| AU-1 | blocking | Primary email never verified | Phase 1 adds verification |
| AU-2 | high | Message polling would keep secured channels open | Only user actions refresh clearance |
| AU-3 | high | Demotion mid-session kept clearance | Access checked on every channel request |
| AU-4 | medium | Signed-out directory could leak members | Limited fields signed out |
| AU-5 | medium | Admin III scope could leak via shared surfaces | All Admin III queries scoped to own channels |
| AU-6 | medium | "Muted" undefined | Read-only platform-wide |
| AU-7 | low | Ban-vote edge cases | Zero ballots = not banned; opener votes separately |
| AU-8 | low | Admin II could read Admin I overrides | Audit visibility scopes |
| AU-9 | low | Admin II prerequisites opaque | Appointment lists missing prerequisites |
| AU-10 | low | 2FA might open `pra` | `pra` is passkey-only |

## Rank capability matrix

| Capability | Admin I | Admin II | Admin III | User I | User II |
| --- | --- | --- | --- | --- | --- |
| Public label | Admin | Admin | Member | Member | Member |
| See hidden profile fields | yes | — | — | — | — |
| Global admin panel | +geo, IP | no geo/IP (Phase 7) | — | — | — |
| Read private channels without membership | audited | — | — | — | — |
| Access `pra` (passkey only) | yes | yes | — | — | — |
| Access `pra-2` | yes | yes | yes | — | — |
| Pin messages | any channel | own channels | own channels | own channels | own channels |
| Open / vote on ban | yes + tie | yes | — | — | — |
| Ban an Admin II | alone | — | — | — | — |

## Phases

1. **Email verification, ranks, permission foundation** — `AUTH-ISSUE-01`, `AUTH-TODO-01`, `RANK-TODO-01`, `RANK-TODO-02`.
2. **Channel types, creation, entry gate, guest retirement** — `CHAN-ISSUE-01`, `CHAN-TODO-01`, `CHAN-TODO-02`.
3. **User tags and mentions** — `MSG-TODO-01` (DOMPurify approved 2026-10-16).
4. **Multi-message pinning** — `MSG-TODO-02`.
5. **Blocking, kicking, reporting** — `MOD-TODO-01`.
6. **Ban vote system** — `MOD-TODO-02`.
7. **Admin panels and channel profile modal** — `ADMN-TODO-01`.
8. **Biometric entry rules and release gate** — `AUTH-TODO-02`, `ROOT-TODO-02`.

## Phase 1 implementation notes (2026-10-16)

- `users.role` replaced by `users.rank` (`App\Enums\Rank`). Migration maps `admin` → `admin_one`, everyone else → `user_two`; run `php artisan vesper:recompute-ranks` after deploying.
- `rank` is deliberately **not** mass-assignable. Only `RankService` and first-run setup change it, via `forceFill`.
- "10+ members" counts active members with a verified email and **excludes the channel creator**. This is an interpretation of Q2; change `RankService::qualifyingChannelCount` if the creator should count.
- `EnsureAdmin` was kept (not renamed) and now checks the `accessAdminPanel` gate. Until Phase 7 the admin panel is Admin I only, because the current panel shows geolocation (Q9).
- In-channel radar UI is gated on `viewGeolocation` (Admin I). Pinning uses `RoomPolicy::pinMessages`.
- "Notify admin" emails go to Admin I only, so Admin II never receives message previews from channels they cannot read.
- Admin II cannot delete their last passkey.
- Every `isAdmin()` call site (24) was reassigned and `isAdmin()` / `isMember()` removed.

## Phase 2 implementation notes (2026-10-16)

- `ChannelAccessService` is the single access decision. `EnsureChannelClearance` runs on every in-channel route and re-checks channel status, expiry and rank admission (AU-3). Membership is not re-checked per request because it can only change mid-session through kicking; Phase 5 adds that check.
- Existing rooms became `prs`. Private channels are now joined only by creating them or accepting an invitation; code + PIN alone no longer admits a non-member. Public secured channels still accept their PIN and enroll the member.
- `Room::pin` is write-only and stores a hash. The migration's PIN hashing is one-way; a rollback restores an empty column.
- Clearance on secured channels lapses 15 minutes after the last member action (POSTs, heartbeat). A clearance flag without a timestamp (sessions from before the deploy) is stamped on first use rather than rejected.
- `pra` interim rule: admits Admin I/II who have an enrolled passkey. A fresh passkey assertion per entry is Phase 8 (`AUTH-TODO-02`).
- Admin I keeps PIN-free entry to every channel (the audited override from Q1).
- New UI strings fall back to English until Phase 7 translates them.

## Phase 3 implementation notes (2026-10-16)

- Tags: `^[a-z][a-z0-9_]{2,29}$`, generated from the name (ASCII-folded, `_2`/`_3` suffixes on collision, reserved words such as `admin` avoided), editable in the profile.
- Mention highlighting is decided at read time: a `@tag` is highlighted when that member is currently an active member or the creator of the channel. No `message_mentions` table (deviation from the original plan).
- Highlighted tags open the member card, looked up by tag among channel members only.
- DOMPurify 3.2.6 sanitizes every formatted message. It is bundled through Vite; the first message batch waits for `DOMContentLoaded` so it is never rendered unsanitized or as plain text by accident.
- Found and fixed two stored XSS bugs in the room page (`MSG-ISSUE-01`, `MSG-ISSUE-02`).
- Composer autocomplete is not built (`MSG-IDEA-01`).

## Phase 4 implementation notes (2026-10-16)

- `message_pins` replaces `rooms.pinned_message_id`; cap of 10 per channel (`MessagePin::MAX_PER_CHANNEL`).
- Timers are paused while pinned and resume on unpin; a message whose timer ran out while pinned disappears when unpinned.
- A message that already self-destructed for everyone cannot be pinned, so pinning cannot revive content its sender chose to burn.
- Pinned messages are shown to every member, including members whose personal countdown had already run out before the pin.
- Channel expiry and purge still remove pinned messages.

