# Changelog

All notable changes to the **Sunday City** platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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
