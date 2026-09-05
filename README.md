# Vesper 🕊️⚡

[![GVS Version](https://img.shields.io/badge/GVS-2609.8.0--bs-059669?style=flat-square&logo=git&logoColor=white)](CHANGELOG.md)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jldesignnetwork)
[![Platform Status](https://img.shields.io/badge/Status-Active%20Defense-0284c7?style=flat-square)]()
[![Pest Tests](https://img.shields.io/badge/Tests-88%20Passing-10b981?style=flat-square&logo=pest&logoColor=white)]()

> **Vesper powered by Ghostwire Protocol** — Discreet, ephemeral communication network with real-time tactical intelligence, zero-trace channels, and granular privacy controls.

---

## Overview

**Vesper** is a hardened, luxury dark-themed private communication platform powered by the **Ghostwire Protocol**. Designed for discreet operations, it combines ephemeral messaging channels, global IP and GPS telemetry tracking, role-based intelligence dossiers, and automated multi-tier privacy and localization engines.

Under the hood, **Ghostwire Protocol** provides end-to-end verified communication pipelines, secure cipher streaming, granular telemetry masking, and zero-trace channel life-cycle management.

---

## Key Features

### 1. Ephemeral Secure Channels (Ghostwire Stream)
- **Passcode & Clearance Gate**: Dual-credential authentication (Channel Code + Secret PIN) with rate limiting against brute-force intrusion.
- **Ghostwire Cipher Stream**: End-to-end verified communication stream with encrypted payloads, inline audio, video, photos, and media attachments.
- **Burn-After-Reading & Self-Destruction**: Configurable TTL timers automatically incinerate channels, messages, attachments, and access logs upon expiration.
- **Immediate Nuke & Lockdown**: One-click tactical killswitches to purge room state, expunge assets, or revoke active session clearance instantly.

### 2. Global Traffic Radar & Geolocation
- **Interactive CARTO Dark Matter Radar**: Leaflet-powered tactical global traffic map rendering real-time operative locations and coordinates.
- **High-Precision GPS Synchronization**: In-chat GPS sync using browser geolocation with automated reverse geocoding to city, country, and ISO country code.
- **Cross-Platform Telemetry Parity**: GPS synchronization reflects across user profiles, session access logs, chat room headers, and the Admin Global Traffic Map simultaneously.

### 3. Location-Based Localization & Language Overrides
- **Multi-Language Support**: Complete translations for English (`en`), Russian (`ru`), French (`fr`), and Italian (`it`).
- **Automated Location Detection**: The interface automatically detects and applies the common language of the operative's registered location (e.g., Italy &rarr; Italian, France &rarr; French, Russia &rarr; Russian, International/Other &rarr; English).
- **User Preference Override**: Operatives can select an explicit language preference in their profile that permanently overrides location-based detection across all devices.

### 4. Comprehensive Member Privacy Controls
- **Selective Concealment**: Members can independently hide their Age, Birthday, Location, and Bio from regular chat members.
- **In-Channel Map Concealment**: When a user hides their location, their coordinates are suppressed (`null`) on all in-channel tactical radar maps (`/c/{room}/radar`), ensuring their pin never appears inside any chat channel. Operatives lists and recent entries display `🔒 Location Hidden` with masked IP addresses for peers.
- **Smart Birth Year Masking**: If a user hides their age while keeping their birthday visible, the system automatically conceals the birth year on the backend, only exposing the Month and Day (e.g. `June 20`) to prevent age deduction.
- **Administrative Intelligence Oversight**: Platform administrators maintain full unmasked visibility (including real-time IP address inspection, Global Traffic Map coordinates, and privacy override badges) across all registered accounts inside the Admin Control Center (`/admin`).

### 5. Multi-Modal Authentication & Account Defense
- **Hardware Biometrics & Passkeys**: WebAuthn/FIDO2 standard support for passwordless authentication using Apple Touch ID, Face ID, or Windows Hello.
- **Two-Factor Authentication (TOTP, RFC 6238)**: Time-based one-time passwords compatible with Apple Passwords (iCloud Keychain), Google Authenticator, and 1Password with pure, offline SVG QR code rendering.
- **Single-Use Emergency Recovery Codes**: 8 cryptographically hashed bypass codes generated during 2FA setup, immediately invalidated and purged upon successful single use.
- **Secondary Emergency Recovery Email**: Cryptographically signed secondary recovery address to safely dispatch 15-minute emergency reset tokens when primary devices are inaccessible.
- **OAuth 2.0 Single Sign-On (Google & Apple)**: Native 1-click authentication with Google and Apple, with full support for Apple's *Hide My Email* private relay.
- **Dual-Channel Security Notifications**: Automatic email security alerts dispatched to both primary and verified secondary emails on critical credential events.

### 6. Operative Profiles & Intelligence Dossiers
- **Custom Avatar System**: High-resolution image uploads (JPG, PNG, WEBP, GIF up to 5MB) with dynamic initials fallback badges.
- **Viewport-Safe Modals**: Ergonomic, `max-h-[90vh]` scrollable modals with pinned headers and action footers for seamless laptop and mobile usability.
- **Admin Dossier Modal**: Instant operative dossiers featuring quick-copy IP addresses, communication status, email alerts, and privacy audit summaries.

---

## Tech Stack

- **Backend**: Laravel 12.x (PHP 8.4+)
- **Frontend**: Blade templates, TailwindCSS, Vanilla JavaScript (ES6+), Leaflet.js
- **Asset Pipeline**: Vite 8.x
- **Testing**: Pest PHP (68 tests, 400 assertions — 100% passing)
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
