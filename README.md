# Vesper 🕊️⚡

[![GVS Version](https://img.shields.io/badge/GVS-2610.0.0--bs-059669?style=flat-square&logo=git&logoColor=white)](CHANGELOG.md)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jldesignnetwork)
[![Platform Status](https://img.shields.io/badge/Status-Active%20Defense-0284c7?style=flat-square)]()
[![Pest Tests](https://img.shields.io/badge/Tests-596%20Passing-10b981?style=flat-square&logo=pest&logoColor=white)]()

> **Vesper Enterprise** — Private, encrypted communications platform with real-time network visualization, ephemeral secure channels, and enterprise-grade privacy controls.

---

## Overview

**Vesper** is an executive, dark-themed private communications platform. Designed for high-security team and client messaging, it combines member-run channels encrypted at rest, verified member geolocation tracking, comprehensive member profiles, and automated multi-tier privacy and localization engines.

---

## Key Features

### 1. Ephemeral Secure Channels & Split Lifecycles
- **Channel Types & Access Gate**: Six channel types — private or public, PIN-secured or free entry, plus two rank-restricted admin channels (`pra`, `pra-2`). Private channels are joined only by invitation; public channels are listed in the directory (`/directory`). Secured channels need their PIN each time they are opened; members with 2FA enter without it, and members who meet the biometric precondition can use a passkey check instead. Either can be switched off per channel. `pra` opens only with a fresh passkey check, for every rank. Channels close after 15 minutes without member activity. PINs are stored only as hashes, entry is rate limited, and every channel requires a signed-in account.
- **Member-Run Channels**: Any member can create channels and edit them from a creator-only settings modal.
- **Pinned Messages**: Channel creators (and Admin I) can pin up to 10 messages, shown in a collapsible tray at the top of the channel. Pinned messages never self-destruct while pinned.
- **Blocking, Kicking & Reports**: Members block each other (hidden both ways, on the server), channel creators remove members and can allow them back, and anyone can report a member. Blocks and kicks against Admin I/II, or an Admin III inside their own channel, file a report instead.
- **Ban Votes**: Admin I and II vote on bans for 72 hours; the member is read-only meanwhile. A strict majority decides, Admin I breaks ties, and a ban signs the member out everywhere and removes their passkeys. Lifting a ban takes a vote with the same rules.
- **Five Ranks**: Admin I (owner), Admin II (appointed, needs biometrics), Admin III, User I and User II (computed from active channels with 10+ verified members). Other members only ever see "Admin" or "Member".
- **User Tags & Mentions**: Every member has a unique `@tag` used to find them and to mention them. Mentions of channel members are highlighted and open their member card. All formatted messages are sanitized with DOMPurify.
- **Encrypted Message Stream**: Messages and attachments are encrypted at rest on the server (not end-to-end), with inline audio, video, photos, and media attachments.
- **Independent Dual TTL Self-Destruct Timers**: Configurable independent countdowns for text content and media attachments (`media_ttl_seconds`), ensuring media can burn separately without prematurely destroying context.
- **Optimistic UI & Low Latency**: 0ms perceptual transmission with instant rendering and deferred background translation (`defer(...)`), reducing server response times to ~15-30ms.
- **Auto-Expiration & Scheduled Retention**: Configurable TTL timers automatically expunge channels, messages, attachments, and access logs upon expiration.
- **Instant Channel Purge**: The channel creator and Admin I can purge channel data and expunge assets instantly. The seeded admin channels cannot be purged.

### 2. Ergonomic Mobile-First & Theming Experience
- **Mobile-First Input Modal**: Automatic bottom-sheet input expansion on mobile screens (< 640px) with dedicated safe-area padding (`pb-safe`), live character counters, and dual TTL selectors.
- **Tactical Theme Engine**: 5 built-in theme presets (Vesper Onyx, Cyberpunk Neon, Tactical Stealth, Solar Amber, Matrix Terminal).
- **Per-User Chat Window Backgrounds**: Local customizable backgrounds supporting textures (Grid, Dots, Carbon, Stars) or custom image URLs with an opacity slider.

### 3. Global Network Activity Map & Geolocation
- **Interactive CARTO Dark Matter Map**: Leaflet-powered global network map rendering real-time verified member locations and coordinates.
- **High-Precision GPS Synchronization**: In-chat GPS sync using browser geolocation with automated reverse geocoding to city, country, and ISO country code.
- **Cross-Platform Parity**: GPS synchronization reflects across user profiles, session access logs, channel headers, and the Admin Network Map simultaneously.

### 4. Location-Based Localization & Language Overrides (16 Languages)
- **Extensive Multi-Language Support**: Full localization across 16 languages: English (`en`), Italian (`it`), French (`fr`), Russian (`ru`), Spanish (`es`), German (`de`), Portuguese (`pt`), Japanese (`ja`), Korean (`ko`), Chinese (`zh`), Uzbek (`uz`), Arabic (`ar`), Turkish (`tr`), Dutch (`nl`), Polish (`pl`), and Malay (`ms`).
- **Bidirectional Support**: Full RTL (right-to-left) text direction formatting for Arabic (`ar`).
- **Automated Location Detection**: The interface automatically detects and applies the common language of the member's registered location.
- **User Preference Override**: Members can select an explicit language preference in their profile that permanently overrides location-based detection across all devices.

### 5. Multilingual Email Template Management
- **Centralized Email Hub (`/admin/emails`)**: Full visual and markdown editing for every platform email template.
- **Dynamic 15-Language Tabs**: Dynamic horizontal scrollable tab navigation with country flags, language codes, and custom status indicators.
- **One-Click Auto-Translation Engine**: Automatically translates templates from English into all 14 target languages with token masking to protect dynamic variables (`{{member_name}}`, `{{pin_code}}`, etc.).
- **Real-Time Live Preview**: Interactive split-pane preview with desktop (600px) and mobile (360px) viewport switches and instant test dispatching.

### 6. Comprehensive Member Privacy Controls
- **Selective Concealment**: Members can independently hide their Age, Birthday, Location, and Bio from regular channel members.
- **In-Channel Map Concealment**: When a member hides their location, their coordinates are suppressed (`null`) on in-channel maps (`/c/{room}/radar`), ensuring their pin never appears. Member lists and recent entries display `🔒 Location Hidden` with masked IP addresses for peers.
- **Smart Birth Year Masking**: If a user hides their age while keeping their birthday visible, the system automatically conceals the birth year on the backend, only exposing the Month and Day (e.g. `June 20`) to prevent age deduction.
- **Administrative Oversight**: Admin I sees everything in the admin console (`/admin`), including IP addresses, the network map and hidden profile fields. Admin II gets the same console without geolocation, IPs or access-log locations; Admin III gets a console limited to the channels they created. Every channel has a profile overview, and the audit log is readable by Admin I (all entries) and Admin II (moderation entries).

### 7. Multi-Modal Authentication & Account Security
- **Hardware Biometrics & Passkeys**: WebAuthn passkeys (Touch ID, Face ID, Windows Hello) verified on the server: challenge, origin, relying party, user presence, signature and counter. Enrolling one requires a verified email plus a verified recovery email or 2FA.
- **Two-Factor Authentication (TOTP, RFC 6238)**: Time-based one-time passwords compatible with Apple Passwords (iCloud Keychain), Google Authenticator, and 1Password with pure, offline SVG QR code rendering.
- **Single-Use Emergency Recovery Codes**: 8 cryptographically hashed bypass codes generated during 2FA setup, immediately invalidated and purged upon successful single use.
- **Secondary Emergency Recovery Email**: Cryptographically signed secondary recovery address to safely dispatch 15-minute emergency reset tokens when primary devices are inaccessible.
- **OAuth 2.0 Single Sign-On (Google & Apple)**: Native 1-click authentication with Google and Apple, with full support for Apple's *Hide My Email* private relay.
- **Dual-Channel Security Notifications**: Automatic email security alerts dispatched to both primary and verified secondary emails on critical credential events.

### 8. Member Profiles & Directory
- **Custom Avatar System**: High-resolution image uploads (JPG, PNG, WEBP, GIF up to 5MB) with dynamic initials fallback badges.
- **Viewport-Safe Modals**: Ergonomic, `max-h-[90vh]` scrollable modals with pinned headers and action footers for seamless laptop and mobile usability.
- **Admin Member Profile Modal**: Detailed member profiles featuring quick-copy IP addresses, communication status, email alert preferences, and privacy audit summaries.

---

## Tech Stack

- **Backend**: Laravel 13.x (PHP 8.4+)
- **Frontend**: Blade templates, TailwindCSS, Vanilla JavaScript (ES6+), Leaflet.js
- **Asset Pipeline**: Vite 8.x
- **Testing**: Pest PHP (596 tests, 2539 assertions — 100% passing, 85.3% line coverage)
- **Mail Handling**: ServBay Mailpit (SMTP port 1025, Web UI port 18025)
- **Local Environment**: ServBay Pro / macOS / PHP 8.4

---

## Installation & Setup

### Prerequisites
- PHP >= 8.4
- Composer
- Node.js & `pnpm` (or `npm`)
- SQLite or MySQL

### Quickstart

1. **Clone the repository**:
   ```bash
   git clone <repository-url> vesper
   cd vesper
   ```

2. **Install dependencies**:
   ```bash
   composer install
   pnpm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Ensure your `.env` contains:
   ```dotenv
   APP_NAME="Vesper"
   APP_LOCALE=en
   APP_FALLBACK_LOCALE=en
   MAIL_MAILER=smtp
   MAIL_HOST=127.0.0.1
   MAIL_PORT=1025
   CARTO_API_KEY=your_carto_key_here
   ```

4. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

5. **Build Assets**:
   ```bash
   pnpm run build
   ```

6. **Serve Locally**:
   ```bash
   php artisan serve
   ```
   Or access via ServBay / local virtual host.

---

## SiteGround Live Deployment Guide (`vesper.mytharios.com`)

When deploying Vesper to SiteGround shared or cloud hosting:

1. **Upload & Document Root**:
   - Upload application files to `/home/customer/www/vesper.mytharios.com/`.
   - In SiteGround Site Tools > **Subdomains**, set the Document Root for `vesper.mytharios.com` to point to the `public/` directory:
     ```text
     /home/customer/www/vesper.mytharios.com/public
     ```
2. **Environment Configuration (`.env`)**:
   ```dotenv
   APP_NAME="Vesper"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://vesper.mytharios.com
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_siteground_dbname
   DB_USERNAME=your_siteground_dbuser
   DB_PASSWORD=your_siteground_dbpassword
   QUEUE_CONNECTION=database
   ```
3. **Storage Symlink & Database Migrations**:
   Run via SSH in your project root:
   ```bash
   php artisan migrate --force
   php artisan storage:link
   pnpm run build
   ```
4. **SiteGround Cron Job Setup**:
   In SiteGround Site Tools > **Devs** > **Cron Jobs**, add a recurring cron job running every minute (`* * * * *`):
   ```bash
   cd /home/customer/www/vesper.mytharios.com && php artisan schedule:run >> /dev/null 2>&1
   ```
   *Note: This single cron automatically executes the Vesper channel self-destruction checks, operations digest, and the queued mail worker without requiring a background supervisor daemon.*

---

## Running Automated Tests

Run the full Pest test suite covering privacy restrictions, GPS synchronization, localization resolution, and channel security:

```bash
vendor/bin/pest
```

---

## Support & Security Disclosure

- **Funding & Support**: [![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jldesignnetwork)
- **Official Security & Incident Reporting**: `jldesignnetwork@icloud.com`
- **Protocol Governance**: JLDN Global Ecosystem Standards

---

## License

Proprietary / Private Communication Platform. All rights reserved.
