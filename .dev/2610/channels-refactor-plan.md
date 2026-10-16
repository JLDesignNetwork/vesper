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
| Q11 | Admin III can be blocked and kicked like any member, except inside channels they created: a block against them does not apply there, and blocking them from inside such a channel files a report. Admin I and II still cannot be blocked or kicked. |
| Q12 | A creator can let a kicked member back (`MOD-IDEA-01`, approved). |
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

## Phase 5 implementation notes (2026-10-16)

- `user_blocks` (one row per direction) and `BlockService`. A block hides both members from each other everywhere content flows: message feed, reply previews, reactions, pinned tray, presence, mention highlighting, member search, member card, email notifications and translation by id. The hidden-member list is cached per user for an hour and cleared on block/unblock.
- Unblocking removes only your own block; if the other member also blocked you, you stay hidden from each other.
- A sender's self-destruct timer no longer waits for members who blocked them (they never see the message).
- Kicking: `room_user.status = kicked` plus `kicked_by_user_id` / `kicked_at`. Only the channel creator can kick; Admin I has no kick override (spec: "kicking can only be done by the channel creator"). `ChannelAccessService` checks the kicked status on entry, on every channel request and for visibility, which closes the per-request membership gap noted in Phase 2.
- Invite links, codes, public entry and Admin I direct invites all refuse a kicked member until the creator lets them back (Q12). Allow back sets the membership to `declined`, so a private channel needs a new invitation and a public one can simply be reopened.
- In private channels, `mayContinue` also ends access for a membership that exists but is no longer active, so a session clearance left over from before a kick cannot be reused after Allow back.
- Block and kick against Admin I/II file a report (`origin` `block_attempt` / `kick_attempt`) and return the same response as a real block or kick (Q3). The member card hides Block and Remove for members shown as Admin.
- Admin III (Q11): blocked and kicked like anyone else (they can never be kicked from their own channel because only its creator kicks). Inside a channel they created, blocks against them are ignored in both directions; a block the Admin III placed still applies. Blocking them from inside their channel files a report with the normal confirmation.
- Reports: `reports` table (reporter, subject, optional channel and message, reason, details, status). Reports and kicks are written to the audit log with moderation visibility, readable by Admin I and II. The review screens are Phase 7.
- Found and fixed `MSG-ISSUE-03`: a reply could quote a message from any channel.

## Phase 6 implementation notes (2026-10-16)

- `ban_cases`, `ban_votes`, `users.banned_at`, `BanVoteService`. Cases are opened by Admin I or II by tag or from an open report.
- Restriction (AU-6) is derived from an open case, not stored, so it ends the moment the case closes. `EnsureNotRestricted` guards posting, reacting, pinning, kicking and allowing back, creating/editing/purging channels and reporting; the profile refuses a tag change. Reading, locking, blocking and joining are still allowed.
- Decision rules (Q6, AU-7): strict majority of ballots cast. On a tie, Admin I's ballot decides; **if Admin I did not vote on a tied case, the member is not banned** (interpretation: no ban without a decision). No ballots = not banned.
- While no Admin II exists, Admin I's ballot closes the case immediately instead of waiting 72 hours (interpretation of "Admin I decides alone").
- Ballots can be changed until the case closes. Voting is refused after 72 hours even if the scheduler has not run yet.
- A ban clears the remember token and deletes passkeys and database sessions. `EnsureAccountNotBanned` (web middleware) signs a banned member out on their next request, which covers password, 2FA, passkey, OAuth and recovery sign-ins. Banned members no longer count toward channel ranks.
- The review page is a standalone `/moderation/bans` page for Admin I/II because the admin panel is still Admin I only; Phase 7 moves it into the Admin I/II panels.
- Not changed: a banned member's channels and messages stay as they are.

## Phase 7 implementation notes (2026-10-16)

- `AdminPanelScope` (`Full` Admin I, `Global` Admin II, `OwnChannels` Admin III) drives one set of admin views. Every `AdminDashboardService` method takes the viewer and scopes its queries; geolocation, IPs, access-log locations and user agents are removed in the data layer, not only hidden in Blade.
- Admin II can view every channel and member but changes only channels they created (RoomPolicy), like any member. Interpretation of "otherwise the view will function the same": same views, no extra powers over other people's channels. Member deletion, direct invitations (which can create accounts), console channel creation, email templates, the network map and GPS sync are Admin I only.
- Admin III: own channels, their members, invitees and removed members, and their access logs. No emails, IPs or locations. Their home stays the profile (they display as members); a profile link opens the panel.
- Hidden profile fields are shown in the panel only to Admin I (and the member), matching Phase 1. Full rank names are shown only to Admin I and the holder.
- Channel profile modal: server-rendered partial loaded into one modal; creator IP only for Admin I.
- Audit log section: Admin I all entries, Admin II moderation entries, Admin III none (AU-8).
- `/moderation/bans` stays a separate page linked from the admin header for Admin I/II.
- Translations: 218 new strings in all 15 languages. ~240 strings that predate the refactor are still English-only (`ROOT-TODO-03`).
- Found and fixed `ADMN-ISSUE-03` (stored XSS in the admin dossier).

## Phase 8 implementation notes (2026-10-16)

- Found and fixed `AUTH-ISSUE-06` (critical): passkey sign-in never verified signatures. WebAuthn is now verified on the server without new dependencies (`CborDecoder`, COSE to PEM, `openssl_verify`). Existing passkeys are upgraded on first use.
- `pra`: `ChannelEntry::PasskeyAssertionRequired`; the gate shows only a passkey button. The check applies to Admin I as well (AU-10 "pra is passkey-only"); Admin I keeps PIN-free entry to every other channel.
- PIN-secured channels: confirmed 2FA keeps PIN-free entry (A1). A passkey no longer silently replaces the PIN; members who meet the precondition get a passkey check as an alternative. Interpretation of "biometric entry replaces PIN entry": the biometric is actually checked.
- The per-channel switch turns off both 2FA and passkey entry for that channel; `pra` has no switch.
- Release gate: Logic and Defense audits run (ledger `.dev/2610/audit-log.json`), coverage 85.3%, version `2610.0.0-bs` in README/CHANGELOG/package.json. Tag and GitHub Release wait for the owner.
- Open owner decisions: `MOD-ISSUE-02` (lifting bans), `ROOT-ISSUE-01` (room page E2E wording), `ROOT-ISSUE-03` (local pnpm store), `ROOT-TODO-03` (pre-2610 strings).
