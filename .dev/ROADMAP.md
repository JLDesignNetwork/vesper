---
title: Vesper Strategic Roadmap
type: web-app
project: vesper
generation: "2610"
gvs_baseline: 2609.11.0-bs
lifecycle: persistent
cleanup: manual
---

# Vesper Strategic Roadmap

> **Project:** Vesper (Laravel 13 / PHP 8.4 / Blade + vanilla JS)
> **Generation Epoch:** `2610`
> **Author:** Jeff Langdon (JL Design Network)
> **Status:** Active — Channels Refactor Phases 1–8 complete; release `2610.0.0-bs` prepared (tag and GitHub Release pending owner approval)

---

## Strategic Vision

Vesper moves from an admin-issued, code-and-PIN channel system to a member-run
communications platform in the style of Telegram or Discord: user-created and
user-moderated channels, a five-rank hierarchy, searchable user tags, multi-message
pinning, blocking and kicking, and a voted ban system.

---

## Generational Backlogs & Horizons

### Generation 2609 (Shipped Baseline — `2609.11.0-bs`)
- [x] Ephemeral secure channels with code + PIN gate, duress passcode, dual text/media TTL.
- [x] Global network map and GPS-synced member geolocation.
- [x] 15-language localization with location-based detection and user override.
- [x] Multilingual email template hub with auto-translation.
- [x] Member privacy controls (age, birthday, location, bio concealment).
- [x] WebAuthn passkeys, TOTP 2FA, recovery codes, recovery email, Google/Apple OAuth.

### Generation 2610 (Channels Refactor — `2610.0.0-bs`)
Full plan: [`2610/channels-refactor-plan.md`](2610/channels-refactor-plan.md). Backlog: [`2610/backlog.json`](2610/backlog.json).

- [x] **Phase 1:** Primary email verification, five-rank model, permission policies, audit log.
- [x] **Phase 2:** Channel types (`prs prf pus puf pra pra-2`), member channel creation, unified entry gate, guest mode retired.
- [x] **Phase 3:** User tags (`@slug`) and server-confirmed mentions.
- [x] **Phase 4:** Multi-message pinning (max 10, exempt from auto-delete).
- [x] **Phase 5:** Blocking, kicking, reporting.
- [x] **Phase 6:** Ban vote system.
- [x] **Phase 7:** Admin I / II / III panels and channel profile modal.
- [x] **Phase 8:** Biometric entry rules and release gate.
- [x] **Post-review:** Lifting bans by vote (`MOD-ISSUE-02`), the older English-only strings translated (`ROOT-TODO-03`), Malay as the 16th language (`ROOT-TODO-04`), escaped translations in scripts (`ROOT-ISSUE-04`), packages linked from the global pnpm store (`ROOT-ISSUE-03`).
- [ ] End-to-end encryption wording or design (`ROOT-ISSUE-01`, owner to revisit); translation review (`ROOT-TODO-05`).

### Future Horizons
- [ ] Verified phone numbers as a biometric precondition (deferred: SMS provider cost).
