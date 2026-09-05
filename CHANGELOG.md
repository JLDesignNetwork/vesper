# Changelog

All notable changes to the **Vesper** platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.7.0] - 2026-09-05

### Added
- **Multi-Page Separation of Concerns (SoC)**:
  - Deconstructed single-page scrolling admin area into dedicated, single-responsibility subpages with independent URL endpoints:
    - **Overview / Command HQ**: `GET /admin` (`admin.dashboard`)
    - **Encrypted Channels**: `GET /admin/channels` (`admin.channels.index`)
    - **Operatives Intelligence**: `GET /admin/operatives` (`admin.operatives.index`)
    - **Global Satellite Radar**: `GET /admin/intel` (`admin.intel.index`)
    - **Transmission & Network Logs**: `GET /admin/logs` (`admin.logs.index`)
  - Created standalone view templates extending `layouts.admin`:
    - `resources/views/admin/channels/index.blade.php`: Dedicated full directory table, frequency counters, and channel establishment controls.
    - `resources/views/admin/operatives/index.blade.php`: Dedicated operatives roster, role breakdowns, and dossier view triggers.
    - `resources/views/admin/intel/index.blade.php`: Full-height interactive Leaflet radar map with dark carto tiles, GPS sync, and geolocated node stream.
    - `resources/views/admin/logs/index.blade.php`: Real-time network and transmission audit logs table.
  - Decoupled `AdminController.php` with dedicated action methods (`index`, `channels`, `operatives`, `intel`, `logs`).
  - Added tailored query methods in `AdminDashboardService.php` (`getOverviewData`, `getChannelsData`, `getOperativesData`, `getIntelData`, `getLogsData`).
- **Sleek Executive Command Header & Multi-Page Navigation**:
  - Implemented luxury dark executive header (`resources/views/admin/partials/header.blade.php`) matching the Ghostwire Protocol aesthetic.
  - Real page routing with active tab highlighting (`request()->routeIs(...)`) across desktop navbar and mobile drawer.
  - Live operational telemetry beacon with animated pulse indicator and live frequency count.
  - Inline GPS status badge with 1-click location sync trigger.
  - Quick Action matrix for rapid channel establishment and clearance invitations.
  - Interactive Operative Profile Dropdown with user avatar, name, administrator badge, 2FA verified indicator, direct navigation to `/channels`, profile/security modal trigger, multi-language switcher (`EN`, `RU`, `FR`, `IT`), and secure logout.
- **Automated Test Coverage**:
  - Added feature tests in `tests/Feature/AdminPlatformTest.php` verifying status 200 for admins across all subpages and redirecting unauthorized members to `/channels` (78 total tests passing project-wide).

## [1.6.0] - 2026-09-05

### Added
- **Post-Login Role-Based Routing**:
  - `admin` accounts automatically routed to Admin Command Center (`/admin`).
  - `member` accounts automatically routed to Operative Channels Hub (`/channels`).
  - Implemented centralized `$user->homeRoute()` on the `User` model, standardizing post-auth redirection across password, WebAuthn biometrics, 2FA challenge, OAuth SSO, and account recovery workflows.
- **Strict Zero-Discovery Operative Channels Hub (`/channels`)**:
  - Operatives can strictly view only channels where they are verified enrolled members.
  - Zero channel exploration, public directory, or arbitrary channel discovery.
  - Interactive cards display channel title, code, role badge, message metrics, and last access timestamp.
- **Channel Invitation Protocol & Token Engine**:
  - `channel_invitations` table tracking secure token links (`/invite/{token}`) and shareable alphanumeric codes (`INV-XXXX-XXXX`).
  - Administrators can directly enroll registered operatives into channels or generate rate-limited / expiring clearance codes.
  - "Redeem Invite Code" modal on `/channels` enabling operatives to claim clearance into new channels.
  - Direct invitation acceptance banner on `/channels` with 1-click Accept / Decline actions.
- **2FA-Guarded Pinless Channel Re-Entry**:
  - Operatives with active 2FA (TOTP authenticator) or WebAuthn hardware biometrics qualify for **1-click pinless entry** (`channels.enter`) into any enrolled channel without entering PINs repeatedly.
  - Operatives without 2FA are routed to the PIN gate with a clear prompt to configure 2FA in profile settings to unlock instant pinless access.
- **Automated Feature Test Suite**:
  - `tests/Feature/MemberChannelsAndInviteTest.php` with 8 comprehensive tests verifying role-based routing, zero-discovery isolation, pinless re-entry, and invite redemption workflows (76 total tests passing project-wide).

---

## [1.5.0] - 2026-09-05

### Added
- **Hardware Biometrics & Passkeys (WebAuthn / FIDO2)**:
  - Native passwordless biometrics support for Apple Touch ID, Face ID, and Windows Hello platform authenticators.
  - Challenge generation, clientDataJSON / authenticatorData parsing, counter verification, and COSE public key enrollment via `WebAuthnService` and `WebAuthnController`.
  - 1-click biometric sign-in directly on `/login` and enrollment interface in Admin and Channel operative profile settings.
- **Two-Factor Authentication (TOTP, RFC 6238)**:
  - Time-based one-time password security compatible with Google Authenticator, Apple Passwords (iCloud Keychain), and 1Password.
  - Built-in offline SVG QR code generator powered by `bacon/bacon-qr-code`, eliminating third-party tracking or external image services.
  - Interactive 2FA challenge login interception (`/two-factor/challenge`) with rolling 30-second window verification and password-protected deactivation.
- **Single-Use Emergency Recovery Codes**:
  - Generation and cryptographically secure hashing of 8 emergency backup recovery codes during 2FA setup.
  - Immediate single-use consumption and automatic purge upon successful authentication bypass with instant dual security alert notifications.
- **Secondary Recovery Email & Emergency Account Reset**:
  - Configurable and cryptographically signed secondary recovery email channel (`/recovery/request`).
  - Emergency 15-minute expiring access tokens dispatched to verified recovery addresses to safely regain access when primary devices are lost.
  - Dual-channel security alert notifications sent to both primary and secondary emails on sensitive security events.
- **OAuth 2.0 Social Single Sign-On (Google & Apple)**:
  - Fast single-click authentication with Google and Apple with full support for Apple's *Hide My Email* private relay.
  - Native implementation using Laravel's `Http` client, eliminating dependency incompatibilities and enabling full mocking test coverage.
- **Localization & Test Suite**:
  - Added comprehensive localization strings across English (`en`), French (`fr`), Italian (`it`), and Russian (`ru`).
  - Created automated test suite `tests/Feature/MultiModalAuthTest.php` with 18 comprehensive tests (400 total assertions across the 68 platform tests, all 100% passing).

### Changed
- **Platform Rebrand to Vesper (Ghostwire Protocol)**:
  - Rebranded platform name from "Sunday City" to **Vesper powered by Ghostwire Protocol**.
  - Updated environment defaults (`APP_NAME="Vesper"`, `MAIL_FROM_ADDRESS="notifications@vesper.local"`).
  - Updated Composer package identity (`vesper/platform`) and NPM package identity (`vesper`).
  - Updated client portal, secret access gate, and cipher badge to `VESPER // GHOSTWIRE CIPHER`.
  - Updated chat channel title bar, streams, and welcome banner to `SECURE GHOSTWIRE STREAM INITIALIZED`.
  - Updated Admin Control Center header with verified `Ghostwire Protocol` badge.
  - Updated email notification templates and localization dictionaries (`en`, `fr`, `it`, `ru`).
  - Updated automated test suites in `RoomSecurityTest` and `RussianLocalizationTest` to assert Vesper brand identity.

### Fixed
- **Gender Column Browser Auto-Translate Collision**: Prevented Safari/Chrome translation collision where Italian `male` (translated to English `Bad`) replaced Male gender values by wrapping gender in `__()` and attaching `translate="no"` / `class="notranslate"` to all gender display elements across cards, dossiers, and admin tables.
- **Mailpit SMTP Integration**: Configured `MAIL_MAILER=smtp` and `MAIL_PORT=1025` for ServBay local Mailpit inbox with verified live delivery.

---

## [1.3.1] - 2026-09-05

### Security & Privacy
- **In-Channel Map Privacy Hardening**: When an operative enables `hide_location = true`, their coordinates (`latitude`, `longitude`) are completely suppressed (`null`) from the in-channel tactical radar API (`/c/{room}/radar`), ensuring their pin never renders on Leaflet maps inside any chat channel.
- **Operatives List & Audit Log Masking**: Peer room participants see `🔒 Location Hidden` and masked IP addresses (`***.***.***.***`) instead of raw city, country, or IP in the Connected Nodes list and Recent Entries audit log.
- **Message Stream Geolocation Masking**: Real-time message polling (`MessageController::index`) suppresses city, country, and flags for messages authored by operatives with `hide_location = true` when queried by channel peers.
- **Admin Section Telemetry Integrity**: Full visibility inside the Admin section (`/admin`) is completely preserved. The Admin Global Traffic Map, Registered Users table, and User Dossier continue to render real-time GPS telemetry, coordinates, and unmasked locations with the `🔒 Hidden` privacy status indicator.
- **Automated Test Coverage**: Added comprehensive feature test verifying coordinates suppression on channel radar maps, masking for peers, and full visibility in the Admin section.

### Fixed
- **Message Transmission 500 Error**: Resolved fatal `Class "App\Http\Controllers\Log" not found` error during message transmission caused by missing `Log`, `Mail`, and `NewMessageNotification` imports in `MessageController.php`. Messages now transmit and display immediately with audio chime without triggering false transmission failure alerts.

---

## [1.3.0] - 2026-09-05

### Added
- **Location-Based Language Detection**: Automatically determines and displays platform language based on the operative's registered location (country code, country, or location text):
  - Italian (`it`) for Italy, San Marino, Vatican City, or Italian cities.
  - French (`fr`) for France, Monaco, Senegal, Ivory Coast, etc.
  - Russian (`ru`) for Russia, Belarus, Kazakhstan, Kyrgyzstan, etc.
  - English (`en`) for international fallback and English-speaking locations.
- **Preferred Language Setting**: Added a user profile setting (`preferred_locale`) allowing members to explicitly choose their interface language (`EN`, `RU`, `FR`, `IT`) or leave as `Auto-detect from Location`. Setting an explicit language strictly overrides location detection across all devices.
- **Header Switcher Persistence**: Changing language via the top-bar locale selector (`/locale/{locale}`) now automatically persists the preference to the authenticated user's account. Added `/locale/auto` route to revert to location auto-detection.
- **Language Intelligence Badges**: Added language preference indicators (`Pref` vs `Auto`) in the Admin Registered Users table and User Dossier modal.
- **Automated Test Suite**: Added 6 tests in `tests/Feature/LocationLanguageTest.php` testing location language mapping, model precedence, middleware enforcement, and switcher persistence.

### Changed
- Database migration added `preferred_locale` column to `users` table.
- `SetLocale` middleware updated to prioritize:
  1. Explicit transient session/URL switcher.
  2. User preferred language setting (`users.preferred_locale`).
  3. Common language of registered location (`User::resolveLocationLocale()`).
  4. Universal fallback locale (`en`).
- `ProfileController` and `RoomController` updated to synchronize session and database locales seamlessly upon profile update, login, or registration.

---

## [1.2.0] - 2026-09-05

### Added
- **Smart Birth Year Masking**: When a user conceals their age (`hide_age = true`) but allows their birthdate (`hide_birthday = false`), the system automatically strips the birth year on the backend, only exposing Month and Day (e.g. `June 20`).
- **Privacy-Aware Viewer Methods**: Centralized viewer clearance logic onto `User` model (`birthdayForViewer()`, `ageForViewer()`, `locationForViewer()`, `bioForViewer()`) ensuring privacy rules are uniformly enforced across chat APIs and live message streams.
- **Privileged Status Badges**: Privileged viewers (admin and self) viewing an account with hidden birth year see the full birthdate annotated with `(Year hidden for members)`.
- **Dossier Age Badges**: Admin User Dossier now displays a `Month/Day Only` tag when an account hides age but keeps birthdate visible.

### Fixed
- Fixed member card fallback in `openMemberCard()` where a hidden age previously fell back to rendering the raw birthdate string in the Age row.
- Prevented birth year leakage in real-time message stream polling (`MessageController::index`).

---

## [1.1.1] - 2026-09-05

### Fixed
- **Modal Viewport Containment & Scrolling**: Re-architected `#profile-modal` and `#user-dossier-modal` in both member chat channels and Admin Dashboard:
  - Capped modal container to `max-h-[90vh]` with `overflow-hidden flex flex-col`.
  - Pinned header (avatar, title, close button) and footer (Cancel, Save buttons) to remain permanently visible without scrolling away.
  - Added `.modal-scroll` sleek custom scrollbar styling with `flex-1 min-h-0 overflow-y-auto` form body to support smaller laptops, netbooks, and mobile landscape views.

---

## [1.1.0] - 2026-09-05

### Added
- **Granular Privacy Controls**: Members can independently hide their Age, Birthday, Location, and Bio from regular chat members via profile settings (`hide_age`, `hide_birthday`, `hide_location`, `hide_bio`).
- **Administrative IP Inspection**:
  - Registered Users Table in Admin Center now features a dedicated **IP Address** column with one-click copy.
  - User Dossier Modal includes a **Latest IP Address** card with copy utility.
  - Access logs automatically track user association via `user_id` foreign key.
- **Admin Visibility Override**: Administrators retain full unmasked visibility of all member attributes across chat cards, dossier modals, and registered user tables, with indicators highlighting private fields.
- **Comprehensive Privacy Test Suite**: Added `tests/Feature/PrivacySettingsTest.php` validating masking, clearance, and admin oversight.

---

## [1.0.1] - 2026-09-05

### Added
- **User-Bound GPS Synchronization**: Tied in-chat GPS sync to user profiles, ensuring coordinates update across individual accounts, session access logs, chat channels, and the Admin Global Traffic Map.
- **CARTO Integration**: Wired `CARTO_API_KEY` configuration into Leaflet map tiles, eliminating watermarks on dark tactical radar tiles.

### Fixed
- Repositioned Registered Users directory in Admin Dashboard from header dropdown into the main content flow.
- Resolved undefined variable `$isAdmin` in `RoomController`.

---

## [1.0.0] - 2026-09-04

### Added
- **Initial Release of Sunday City**:
  - Ephemeral communication channels with Code and PIN access clearance.
  - Burn-After-Reading self-destruct mechanism and TTL expiration timers.
  - Immediate Channel Lock and Nuke killswitch options.
  - Multi-format media attachments (images, video, audio, files).
  - Admin Command Center with channel lifecycle management.
  - Multilingual localization support for English (`en`), Russian (`ru`), French (`fr`), and Italian (`it`).
  - Dark luxury glassmorphism UI built with TailwindCSS and Vite.
