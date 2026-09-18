# Vesper 🕊️⚡

[![GVS Version](https://img.shields.io/badge/GVS-2609.10.0--bs-059669?style=flat-square&logo=git&logoColor=white)](CHANGELOG.md)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jldesignnetwork)
[![Platform Status](https://img.shields.io/badge/Status-Active%20Defense-0284c7?style=flat-square)]()
[![Pest Tests](https://img.shields.io/badge/Tests-124%20Passing-10b981?style=flat-square&logo=pest&logoColor=white)]()

> **Vesper Enterprise** — Private, encrypted communications platform with real-time network visualization, ephemeral secure channels, and enterprise-grade privacy controls.

---

## Overview

**Vesper** is an executive, dark-themed private communications platform. Designed for high-security team and client messaging, it combines end-to-end encrypted channels, verified member geolocation tracking, comprehensive member profiles, and automated multi-tier privacy and localization engines.

---

## Key Features

### 1. Ephemeral Secure Channels
- **Passcode & Access Gate**: Dual-credential authentication (Channel Code + Secret PIN) with rate limiting against brute-force intrusion.
- **Encrypted Message Stream**: End-to-end verified communication stream with encrypted payloads, inline audio, video, photos, and media attachments.
- **Auto-Expiration & Scheduled Retention**: Configurable TTL timers automatically expunge channels, messages, attachments, and access logs upon expiration.
- **Instant Channel Purge**: Administrative and host controls to purge channel data, expunge assets, or revoke active invitations instantly.

### 2. Global Network Activity Map & Geolocation
- **Interactive CARTO Dark Matter Map**: Leaflet-powered global network map rendering real-time verified member locations and coordinates.
- **High-Precision GPS Synchronization**: In-chat GPS sync using browser geolocation with automated reverse geocoding to city, country, and ISO country code.
- **Cross-Platform Parity**: GPS synchronization reflects across user profiles, session access logs, channel headers, and the Admin Network Map simultaneously.

### 3. Location-Based Localization & Language Overrides (15 Languages)
- **Extensive Multi-Language Support**: Full localization across 15 languages: English (`en`), Italian (`it`), French (`fr`), Russian (`ru`), Spanish (`es`), German (`de`), Portuguese (`pt`), Japanese (`ja`), Korean (`ko`), Chinese (`zh`), Uzbek (`uz`), Arabic (`ar`), Turkish (`tr`), Dutch (`nl`), and Polish (`pl`).
- **Bidirectional Support**: Full RTL (right-to-left) text direction formatting for Arabic (`ar`).
- **Automated Location Detection**: The interface automatically detects and applies the common language of the member's registered location.
- **User Preference Override**: Members can select an explicit language preference in their profile that permanently overrides location-based detection across all devices.

### 4. Multilingual Email Template Management
- **Centralized Email Hub (`/admin/emails`)**: Full visual and markdown editing for all 9 platform dispatch templates.
- **Dynamic 15-Language Tabs**: Dynamic horizontal scrollable tab navigation with country flags, language codes, and custom status indicators.
- **One-Click Auto-Translation Engine**: Automatically translates templates from English into all 14 target languages with token masking to protect dynamic variables (`{{member_name}}`, `{{pin_code}}`, etc.).
- **Real-Time Live Preview**: Interactive split-pane preview with desktop (600px) and mobile (360px) viewport switches and instant test dispatching.

### 5. Comprehensive Member Privacy Controls
- **Selective Concealment**: Members can independently hide their Age, Birthday, Location, and Bio from regular channel members.
- **In-Channel Map Concealment**: When a member hides their location, their coordinates are suppressed (`null`) on in-channel maps (`/c/{room}/radar`), ensuring their pin never appears. Member lists and recent entries display `🔒 Location Hidden` with masked IP addresses for peers.
- **Smart Birth Year Masking**: If a user hides their age while keeping their birthday visible, the system automatically conceals the birth year on the backend, only exposing the Month and Day (e.g. `June 20`) to prevent age deduction.
- **Administrative Oversight**: Platform administrators maintain full unmasked visibility (including real-time IP address inspection, Global Network Map coordinates, and privacy override badges) across all registered accounts inside the Admin Console (`/admin`).

### 6. Multi-Modal Authentication & Account Security
- **Hardware Biometrics & Passkeys**: WebAuthn/FIDO2 standard support for passwordless authentication using Apple Touch ID, Face ID, or Windows Hello.
- **Two-Factor Authentication (TOTP, RFC 6238)**: Time-based one-time passwords compatible with Apple Passwords (iCloud Keychain), Google Authenticator, and 1Password with pure, offline SVG QR code rendering.
- **Single-Use Emergency Recovery Codes**: 8 cryptographically hashed bypass codes generated during 2FA setup, immediately invalidated and purged upon successful single use.
- **Secondary Emergency Recovery Email**: Cryptographically signed secondary recovery address to safely dispatch 15-minute emergency reset tokens when primary devices are inaccessible.
- **OAuth 2.0 Single Sign-On (Google & Apple)**: Native 1-click authentication with Google and Apple, with full support for Apple's *Hide My Email* private relay.
- **Dual-Channel Security Notifications**: Automatic email security alerts dispatched to both primary and verified secondary emails on critical credential events.

### 7. Member Profiles & Directory
- **Custom Avatar System**: High-resolution image uploads (JPG, PNG, WEBP, GIF up to 5MB) with dynamic initials fallback badges.
- **Viewport-Safe Modals**: Ergonomic, `max-h-[90vh]` scrollable modals with pinned headers and action footers for seamless laptop and mobile usability.
- **Admin Member Profile Modal**: Detailed member profiles featuring quick-copy IP addresses, communication status, email alert preferences, and privacy audit summaries.

---

## Tech Stack

- **Backend**: Laravel 12.x (PHP 8.4+)
- **Frontend**: Blade templates, TailwindCSS, Vanilla JavaScript (ES6+), Leaflet.js
- **Asset Pipeline**: Vite 8.x
- **Testing**: Pest PHP (124 tests, 852 assertions — 100% passing)
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
