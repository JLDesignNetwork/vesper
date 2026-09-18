    <!-- Member / User Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto p-3 sm:p-4 flex items-center justify-center">
        <div class="w-full max-w-md my-auto bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden font-sans">
            <div class="flex items-center justify-between p-4 sm:p-5 pb-3 sm:pb-4 border-b border-slate-800 shrink-0 bg-slate-900">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300 font-bold font-mono text-sm">
                        {{ strtoupper(substr($adminUser->name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white tracking-tight">{{ __('Profile Settings') }}</h3>
                    </div>
                </div>
                <button type="button" onclick="closeProfileModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <form id="profile-form" onsubmit="saveProfile(event)" class="flex flex-col flex-1 min-h-0">
                @csrf

                <div class="overflow-y-auto p-4 sm:p-5 space-y-3.5 text-xs font-sans flex-1 modal-scroll">
                    <div id="profile-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>

                    <!-- Custom Avatar Uploader -->
                    <div class="flex items-center gap-3.5 p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                        <div class="relative group shrink-0">
                            <div id="profile-avatar-preview-wrap" class="w-14 h-14 rounded-2xl overflow-hidden bg-emerald-500/20 border-2 border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold font-mono text-lg">
                                @if($adminUser->avatar_path)
                                    <img id="profile-avatar-preview-img" src="{{ $adminUser->avatarUrl() }}" class="w-full h-full object-cover" alt="">
                                    <span id="profile-avatar-preview-initial" class="hidden">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                                @else
                                    <img id="profile-avatar-preview-img" src="" class="w-full h-full object-cover hidden" alt="">
                                    <span id="profile-avatar-preview-initial">{{ strtoupper(substr($adminUser->name, 0, 1)) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="text-[11px] font-semibold text-white">{{ __('Profile Picture') }}</div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick="document.getElementById('avatar-file-input').click()"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-medium cursor-pointer transition-colors"
                                >
                                    {{ __('Upload Photo') }}
                                </button>
                                <button
                                    type="button"
                                    id="remove-avatar-btn"
                                    onclick="markAvatarForRemoval()"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium cursor-pointer transition-colors {{ $adminUser->avatar_path ? '' : 'hidden' }}"
                                >
                                    {{ __('Remove') }}
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-500 font-mono">{{ __('JPG, PNG, WEBP, GIF (Max 5MB)') }}</p>
                        </div>
                        <input type="file" id="avatar-file-input" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                        <input type="hidden" id="remove-avatar-flag" name="remove_avatar" value="0">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Display Name / Username') }} <span class="text-rose-400">*</span></label>
                        <input
                            type="text"
                            name="name"
                            id="profile-input-name"
                            required
                            value="{{ $adminUser->name }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Email Address') }} <span class="text-rose-400">*</span></label>
                        <input
                            type="email"
                            name="email"
                            id="profile-input-email"
                            required
                            value="{{ $adminUser->email }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">{{ __('Birthday') }}</label>
                            <input
                                type="date"
                                name="birthday"
                                id="profile-input-birthday"
                                value="{{ $adminUser->birthday?->format('Y-m-d') }}"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:outline-none focus:border-emerald-500"
                            >
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">{{ __('Gender') }}</label>
                            <select
                                name="gender"
                                id="profile-input-gender"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                            >
                                <option value="">{{ __('Prefer not to say') }}</option>
                                <option value="Male" {{ $adminUser->gender === 'Male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                                <option value="Female" {{ $adminUser->gender === 'Female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                                <option value="Non-binary" {{ $adminUser->gender === 'Non-binary' ? 'selected' : '' }}>{{ __('Non-binary') }}</option>
                                <option value="Other" {{ $adminUser->gender === 'Other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="font-medium text-slate-300">{{ __('Location') }}</label>
                            <button
                                type="button"
                                id="profile-detect-gps-btn"
                                onclick="detectProfileGps()"
                                class="text-[10px] text-cyan-400 hover:text-cyan-300 font-mono flex items-center gap-1 cursor-pointer transition-colors"
                                title="{{ __('Auto-detect location via browser GPS') }}"
                            >
                                <span>📍</span>
                                <span id="profile-detect-gps-text">{{ __('Detect GPS') }}</span>
                            </button>
                        </div>
                        <input
                            type="text"
                            name="location"
                            id="profile-input-location"
                            placeholder="e.g. Rome, Italy"
                            value="{{ $adminUser->location }}"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('Bio / Status') }}</label>
                        <textarea
                            name="bio"
                            id="profile-input-bio"
                            rows="2"
                            placeholder="A brief note about yourself..."
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500"
                        >{{ $adminUser->bio }}</textarea>
                    </div>

                    <!-- Language Preference Setting -->
                    <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 space-y-1.5 font-sans">
                        <div class="flex items-center justify-between">
                            <label class="block font-medium text-slate-300 text-xs flex items-center gap-1.5">
                                <span>🌐</span>
                                <span>{{ __('Language Preference') }}</span>
                            </label>
                            @php
                                $detectedAdminLang = $adminUser->resolveLocationLocale();
                                $supportedAdminLangs = \App\Services\LanguageService::supported();
                            @endphp
                            <span class="text-[10px] font-mono text-emerald-400/80">
                                {{ __('Detected') }}: {{ $supportedAdminLangs[$detectedAdminLang]['name'] ?? strtoupper($detectedAdminLang) }}
                            </span>
                        </div>
                        <select
                            name="preferred_locale"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs focus:outline-none focus:border-emerald-500 font-sans cursor-pointer"
                        >
                            <option value="auto" {{ empty($adminUser->preferred_locale) ? 'selected' : '' }}>
                                🌐 {{ __('Auto-detect from Registered Location') }} ({{ $supportedAdminLangs[$detectedAdminLang]['name'] ?? strtoupper($detectedAdminLang) }})
                            </option>
                            @foreach($supportedAdminLangs as $code => $lang)
                                <option value="{{ $code }}" {{ $adminUser->preferred_locale === $code ? 'selected' : '' }}>
                                    {{ $lang['flag'] }} {{ $lang['name'] }} ({{ strtoupper($code) }}) - {{ $lang['native'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 leading-snug">
                            {{ __('Selecting a preferred language overrides your registered location language across all devices.') }}
                        </p>
                    </div>

                    <!-- Privacy & Visibility Settings -->
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-semibold text-white uppercase font-mono tracking-wider flex items-center gap-1.5">
                                <span>🔒</span>
                                <span>{{ __('Privacy & Visibility') }}</span>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500">{{ __('Member Restrictions') }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-snug">
                            {{ __('Choose which details are concealed when regular members inspect your profile in chat. Hiding age conceals your birth year (Month & Day remain visible unless Birthday is also hidden).') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-xs">
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_age"
                                    value="1"
                                    {{ $adminUser->hide_age ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Age') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_birthday"
                                    value="1"
                                    {{ $adminUser->hide_birthday ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Birthday') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_location"
                                    value="1"
                                    {{ $adminUser->hide_location ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Location') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="hide_bio"
                                    value="1"
                                    {{ $adminUser->hide_bio ? 'checked' : '' }}
                                    class="rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                >
                                <span class="text-slate-300 text-[11px]">{{ __('Hide Bio') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Security, Biometrics & Account Defense -->
                    <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-semibold text-white uppercase font-mono tracking-wider flex items-center gap-1.5">
                                <span>🛡️</span>
                                <span>{{ __('Security & Clearances') }}</span>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-400">{{ __('MFA & Biometrics') }}</span>
                        </div>

                        <!-- Hardware Biometrics (Touch ID / Face ID) -->
                        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">🔒</span>
                                    <div>
                                        <div class="text-xs font-semibold text-white">{{ __('Hardware Biometrics / Passkeys') }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono" id="biometrics-count-label">
                                            {{ $adminUser->webauthnCredentials->count() }} {{ __('registered keys') }}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onclick="enrollCurrentDeviceBiometrics()"
                                    id="enroll-bio-btn"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 font-mono text-[11px] cursor-pointer transition-colors"
                                >
                                    + {{ __('Enroll This Device') }}
                                </button>
                            </div>

                            <div id="biometrics-keys-list" class="space-y-1.5 pt-1">
                                @forelse($adminUser->webauthnCredentials as $credential)
                                    <div class="flex items-center justify-between px-2 py-1 rounded bg-slate-950/60 border border-slate-800/80 text-[11px] font-mono text-slate-300">
                                        <div class="flex items-center gap-1.5 truncate">
                                            <span class="text-emerald-400">⚡</span>
                                            <span class="truncate">{{ $credential->device_name }}</span>
                                            <span class="text-[9px] text-slate-500">({{ $credential->created_at->format('M d') }})</span>
                                        </div>
                                        <button
                                            type="button"
                                            onclick="revokeBiometricKey({{ $credential->id }}, this)"
                                            class="text-rose-400 hover:text-rose-300 text-[10px] ml-2 cursor-pointer"
                                            title="{{ __('Revoke Key') }}"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-[10px] text-slate-500 font-mono">{{ __('No biometric keys enrolled on this identity yet.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Two-Factor Authentication (TOTP) -->
                        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">🔑</span>
                                    <div>
                                        <div class="text-xs font-semibold text-white">{{ __('Authenticator App (TOTP 2FA)') }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            @if($adminUser->hasTwoFactor())
                                                <span class="text-emerald-400">● {{ __('Active & Enforced') }}</span>
                                            @else
                                                <span class="text-slate-500">○ {{ __('Not Configured') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($adminUser->hasTwoFactor())
                                    <button
                                        type="button"
                                        onclick="openDisable2faModal()"
                                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 font-mono text-[11px] cursor-pointer transition-colors"
                                    >
                                        {{ __('Disable') }}
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        onclick="openTwoFactorSetupModal()"
                                        class="px-2.5 py-1 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 font-mono text-[11px] cursor-pointer transition-colors"
                                    >
                                        {{ __('Setup 2FA') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Secondary Recovery Email -->
                        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-white flex items-center gap-1.5">
                                    <span>✉️</span>
                                    <span>{{ __('Secondary Recovery Email') }}</span>
                                </div>
                                <span class="text-[10px] font-mono {{ $adminUser->hasVerifiedRecoveryEmail() ? 'text-emerald-400' : 'text-amber-400' }}">
                                    {{ $adminUser->hasVerifiedRecoveryEmail() ? __('Verified') : ($adminUser->recovery_email ? __('Pending') : __('Unset')) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="email"
                                    id="profile-recovery-email-input"
                                    value="{{ $adminUser->recovery_email }}"
                                    placeholder="backup@securemail.com"
                                    class="flex-1 px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:outline-none focus:border-emerald-500"
                                >
                                <button
                                    type="button"
                                    onclick="saveRecoveryEmail()"
                                    id="save-recovery-email-btn"
                                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-mono cursor-pointer transition-colors shrink-0"
                                >
                                    {{ __('Save') }}
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-500 font-mono leading-tight">
                                {{ __('Emergency recovery links and critical account security alerts are dispatched to this address.') }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">{{ __('New Password') }} <span class="text-slate-500 text-[10px]">({{ __('leave blank to keep current') }})</span></label>
                        <input
                            type="password"
                            name="password"
                            id="profile-input-password"
                            placeholder="••••••••"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:outline-none focus:border-emerald-500"
                        >
                    </div>

                    <div class="pt-1">
                        <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800 hover:border-emerald-500/40 cursor-pointer transition-colors select-none">
                            <input
                                type="checkbox"
                                name="email_notifications"
                                id="profile-input-email-notifications"
                                value="1"
                                {{ $adminUser->email_notifications ? 'checked' : '' }}
                                class="mt-0.5 rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                            >
                            <div class="text-xs">
                                <span class="font-medium text-white block">{{ __('Email Notifications') }}</span>
                                <span class="text-slate-400 text-[11px] block mt-0.5 leading-snug">{{ __('Receive email notifications for critical network transmissions and alerts.') }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="p-3.5 sm:p-4 border-t border-slate-800 bg-slate-950/70 flex items-center justify-end gap-2 font-mono shrink-0">
                    <button
                        type="button"
                        onclick="closeProfileModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer text-xs"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        id="save-profile-btn"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer text-xs"
                    >
                        {{ __('Save Profile') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Two-Factor Authentication Setup Modal -->
    <div id="two-factor-setup-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto p-3 sm:p-4 flex items-center justify-center font-sans">
        <div class="w-full max-w-md my-auto bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-800 bg-slate-900 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-400 font-bold">
                        🔑
                    </div>
                    <h3 class="text-sm font-semibold text-white tracking-tight">{{ __('Configure Authenticator App (2FA)') }}</h3>
                </div>
                <button type="button" onclick="closeTwoFactorSetupModal()" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
            </div>

            <!-- Step 1: Scan QR Code & Enter Code -->
            <div id="two-factor-step-1" class="p-4 sm:p-5 space-y-4 overflow-y-auto modal-scroll text-xs">
                <p class="text-slate-400 leading-relaxed">
                    {{ __('Scan this QR code with your authenticator app (Google Authenticator, Apple Passwords / iCloud Keychain, 1Password, or Authy), then enter the 6-digit confirmation code below.') }}
                </p>

                <!-- QR Code Container -->
                <div class="flex flex-col items-center justify-center p-4 bg-slate-950 rounded-xl border border-slate-800">
                    <div id="two-factor-qr-code-wrap" class="w-48 h-48 flex items-center justify-center bg-slate-950 rounded-lg overflow-hidden border border-emerald-500/30">
                        <span class="text-slate-500 font-mono text-xs">{{ __('Generating QR Code...') }}</span>
                    </div>
                    <div class="mt-3 text-center">
                        <span class="text-[10px] text-slate-500 font-mono block">{{ __('Manual Entry Secret Key:') }}</span>
                        <code id="two-factor-secret-code" class="text-xs font-mono font-bold text-emerald-400 select-all tracking-wider"></code>
                    </div>
                </div>

                <div id="two-factor-setup-alert" class="hidden p-2.5 rounded-xl text-xs font-mono"></div>

                <div>
                    <label class="block font-medium text-slate-300 mb-1.5 uppercase tracking-wider font-mono text-[11px]">
                        {{ __('6-Digit Verification Code') }}
                    </label>
                    <input
                        type="text"
                        id="two-factor-confirm-code-input"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="000000"
                        class="w-full text-center tracking-[0.5em] font-mono text-xl font-bold py-2.5 px-3 rounded-xl bg-slate-950 border border-slate-800 text-emerald-400 placeholder-slate-700 focus:outline-none focus:border-cyan-500"
                    >
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        onclick="closeTwoFactorSetupModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors cursor-pointer text-xs"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        onclick="submitConfirmTwoFactor()"
                        id="submit-confirm-2fa-btn"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-teal-600 hover:from-cyan-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer text-xs"
                    >
                        {{ __('Verify & Enable 2FA') }}
                    </button>
                </div>
            </div>

            <!-- Step 2: Emergency Backup Recovery Codes Display -->
            <div id="two-factor-step-2" class="p-4 sm:p-5 space-y-4 overflow-y-auto modal-scroll text-xs hidden">
                <div class="p-3 rounded-xl bg-amber-950/40 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2.5">
                    <span class="text-base">⚠️</span>
                    <span>{{ __('Save these emergency recovery codes in a secure location. Each code can be used once if you lose access to your authenticator app.') }}</span>
                </div>

                <div id="emergency-recovery-codes-grid" class="grid grid-cols-2 gap-2 p-3 bg-slate-950 rounded-xl border border-slate-800 font-mono text-center text-xs text-white">
                    <!-- Populated dynamically via JS -->
                </div>

                <div class="flex items-center justify-between gap-2 pt-2">
                    <button
                        type="button"
                        onclick="copyRecoveryCodes()"
                        id="copy-recovery-codes-btn"
                        class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer text-xs font-mono"
                    >
                        {{ __('Copy All Codes') }}
                    </button>
                    <button
                        type="button"
                        onclick="finishTwoFactorSetup()"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-medium shadow-lg transition-all cursor-pointer text-xs"
                    >
                        {{ __('Done') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
