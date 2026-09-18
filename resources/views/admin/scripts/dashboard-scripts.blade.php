    <script>
        function openCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            generateChannelCode();
            generatePasscode();
        }

        function closeCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
        }

        function openInviteModal(roomId, roomCode, roomTitle, roomPin) {
            document.getElementById('invite-channel-form').action = `/admin/channels/${roomId}/invite`;
            document.getElementById('invite-channel-subtitle').innerText = `Target Channel: [${roomCode}] ${roomTitle}`;
            const pinEl = document.getElementById('invite-channel-pin');
            if (pinEl) {
                pinEl.innerText = roomPin || '••••••';
            }
            document.getElementById('invite-channel-modal').classList.remove('hidden');
        }

        function closeInviteModal() {
            document.getElementById('invite-channel-modal').classList.add('hidden');
        }

        function openEditModal(room) {
            document.getElementById('edit-channel-form').action = `/admin/channels/${room.id}`;
            document.getElementById('edit-input-code').value = room.code;
            document.getElementById('edit-input-title').value = room.title || '';
            document.getElementById('edit-input-pin').value = room.pin || '';
            document.getElementById('edit-input-status').value = room.status || 'active';

            const langs = room.languages || ['en', 'ru', 'fr', 'it'];
            ['en', 'ru', 'fr', 'it'].forEach(code => {
                const el = document.getElementById(`edit-lang-${code}`);
                if (el) {
                    el.checked = langs.includes(code);
                }
            });

            const notifyEl = document.getElementById('edit-notify-admin');
            if (notifyEl) {
                notifyEl.checked = !!room.notify_admin;
            }

            document.getElementById('edit-modal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
        }

        function openUserDossier(user) {
            const avatarUrl = user.avatar_url || (user.avatar_path ? ('/storage/' + user.avatar_path) : null);
            const dossierImg = document.getElementById('dossier-avatar-img');
            const dossierInitial = document.getElementById('dossier-avatar');
            if (avatarUrl) {
                if (dossierImg) {
                    dossierImg.src = avatarUrl;
                    dossierImg.classList.remove('hidden');
                }
                if (dossierInitial) dossierInitial.classList.add('hidden');
            } else {
                if (dossierImg) {
                    dossierImg.src = '';
                    dossierImg.classList.add('hidden');
                }
                if (dossierInitial) {
                    dossierInitial.textContent = (user.name || 'U').substring(0, 2).toUpperCase();
                    dossierInitial.classList.remove('hidden');
                }
            }

            document.getElementById('dossier-name').textContent = user.name || 'Operative';
            document.getElementById('dossier-email').textContent = user.email || '—';

            // Latest IP
            const ipEl = document.getElementById('dossier-ip-val');
            if (ipEl) {
                ipEl.textContent = user.latest_ip || '—';
            }

            // Age & Birthday
            let ageText = '—';
            if (user.birthday) {
                const bday = new Date(user.birthday);
                const ageYears = Math.floor((new Date() - bday) / (365.25 * 24 * 60 * 60 * 1000));
                ageText = `${ageYears} yrs (${user.birthday.substring(0, 10)})`;
            }
            const ageEl = document.getElementById('dossier-age');
            if (ageEl) {
                let badges = '';
                if (user.hide_age) badges += ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 {{ __('Age Hidden') }}</span>';
                if (user.hide_birthday) badges += ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 {{ __('Bday Hidden') }}</span>';
                if (user.hide_age && !user.hide_birthday) badges += ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-300 border border-sky-500/20 font-mono">{{ __('Month/Day Only') }}</span>';
                ageEl.innerHTML = `<span>${ageText}</span>${badges}`;
            }

            const genderLabels = {
                'Male': "{{ __('Male') }}",
                'Female': "{{ __('Female') }}",
                'Non-binary': "{{ __('Non-binary') }}",
                'Other': "{{ __('Other') }}"
            };
            document.getElementById('dossier-gender').textContent = user.gender ? (genderLabels[user.gender] || user.gender) : '—';

            // Location
            const locEl = document.getElementById('dossier-location');
            if (locEl) {
                let locBadge = user.hide_location ? ' <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-mono">🔒 {{ __('Hidden') }}</span>' : '';
                locEl.innerHTML = `<span>${user.location || '—'}</span>${locBadge}`;
            }

            document.getElementById('dossier-alerts').textContent = user.email_notifications ? "{{ __('Enabled') }}" : "{{ __('Disabled') }}";

            // Language
            const langEl = document.getElementById('dossier-language');
            if (langEl) {
                const langNames = {
                    'en': "{{ __('English (EN)') }}",
                    'ru': "{{ __('Russian (RU)') }}",
                    'fr': "{{ __('French (FR)') }}",
                    'it': "{{ __('Italian (IT)') }}"
                };
                if (user.preferred_locale) {
                    const name = langNames[user.preferred_locale] || user.preferred_locale.toUpperCase();
                    langEl.innerHTML = `<span class="text-sky-300 font-medium">${name}</span> <span class="text-[9px] px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-300 border border-sky-500/20 font-mono">{{ __('User Override') }}</span>`;
                } else {
                    const eff = user.effective_locale || user.location_locale || 'en';
                    const name = langNames[eff] || eff.toUpperCase();
                    langEl.innerHTML = `<span class="text-slate-300 font-medium">${name}</span> <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700 font-mono">{{ __('Auto (Location)') }}</span>`;
                }
            }

            document.getElementById('dossier-joined').textContent = user.created_at ? new Date(user.created_at).toLocaleString() : '—';

            // Bio
            const bioEl = document.getElementById('dossier-bio');
            const bioBadgeEl = document.getElementById('dossier-bio-badge');
            if (bioEl) {
                bioEl.textContent = user.bio || "{{ __('No intelligence notes or biography recorded.') }}";
            }
            if (bioBadgeEl) {
                if (user.hide_bio) {
                    bioBadgeEl.classList.remove('hidden');
                } else {
                    bioBadgeEl.classList.add('hidden');
                }
            }

            // Privacy summary
            const privacyEl = document.getElementById('dossier-privacy-summary');
            if (privacyEl) {
                const hiddenItems = [];
                if (user.hide_age) hiddenItems.push(user.hide_birthday ? "{{ __('Age') }}" : "{{ __('Age (Year concealed)') }}");
                if (user.hide_birthday) hiddenItems.push("{{ __('Birthday') }}");
                if (user.hide_location) hiddenItems.push("{{ __('Location') }}");
                if (user.hide_bio) hiddenItems.push("{{ __('Bio') }}");

                if (hiddenItems.length > 0) {
                    privacyEl.innerHTML = `<span class="text-amber-400 font-mono">🔒 {{ __('Concealed from members') }}: <strong>${hiddenItems.join(', ')}</strong> ({{ __('Admin overrides and sees all') }})</span>`;
                } else {
                    privacyEl.innerHTML = `<span class="text-emerald-400 font-mono">✓ {{ __('All profile fields visible to members') }}</span>`;
                }
            }

            const editMyProfileBtn = document.getElementById('dossier-edit-my-profile-btn');
            if (editMyProfileBtn) {
                if (user.id === {{ Auth::id() }}) {
                    editMyProfileBtn.classList.remove('hidden');
                } else {
                    editMyProfileBtn.classList.add('hidden');
                }
            }

            document.getElementById('user-dossier-modal').classList.remove('hidden');
        }

        function copyDossierIp() {
            const ip = document.getElementById('dossier-ip-val')?.textContent?.trim();
            if (ip && ip !== '—') {
                copyText(ip, document.getElementById('dossier-copy-ip-btn'), '{{ __("IP copied!") }}');
            }
        }

        function closeUserDossier() {
            document.getElementById('user-dossier-modal').classList.add('hidden');
        }

        // Profile Modal Handlers
        function openProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (modal) modal.classList.add('hidden');
        }

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    showToast('{{ __("Image exceeds the 5MB file size limit.") }}');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('profile-avatar-preview-img');
                    const initial = document.getElementById('profile-avatar-preview-initial');
                    const removeBtn = document.getElementById('remove-avatar-btn');
                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove('hidden');
                    }
                    if (initial) initial.classList.add('hidden');
                    if (removeBtn) removeBtn.classList.remove('hidden');
                    const removeFlag = document.getElementById('remove-avatar-flag');
                    if (removeFlag) removeFlag.value = '0';
                };
                reader.readAsDataURL(file);
            }
        }

        function markAvatarForRemoval() {
            const fileInput = document.getElementById('avatar-file-input');
            if (fileInput) fileInput.value = '';
            const img = document.getElementById('profile-avatar-preview-img');
            const initial = document.getElementById('profile-avatar-preview-initial');
            const removeBtn = document.getElementById('remove-avatar-btn');
            if (img) {
                img.src = '';
                img.classList.add('hidden');
            }
            if (initial) initial.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.add('hidden');
            const removeFlag = document.getElementById('remove-avatar-flag');
            if (removeFlag) removeFlag.value = '1';
        }

        async function saveProfile(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('save-profile-btn');
            const alertEl = document.getElementById('profile-alert');
            const formData = new FormData(form);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            alertEl.classList.add('hidden');

            try {
                const res = await fetch('{{ route("profile.update") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-emerald-950/60 border border-emerald-500/40 text-emerald-300';
                    alertEl.textContent = '✓ ' + (data.message || 'Profile saved.');
                    alertEl.classList.remove('hidden');

                    // Update header avatar & name
                    const headerImg = document.getElementById('header-avatar-img');
                    const headerInitial = document.getElementById('header-avatar-initial');
                    const headerName = document.getElementById('header-user-name');
                    const headerSubName = document.getElementById('header-subtitle-name');
                    if (headerName && data.user.name) headerName.textContent = data.user.name;
                    if (headerSubName && data.user.name) headerSubName.textContent = data.user.name;

                    if (data.user.avatar_url) {
                        if (headerImg) {
                            headerImg.src = data.user.avatar_url;
                            headerImg.classList.remove('hidden');
                        }
                        if (headerInitial) headerInitial.classList.add('hidden');
                    } else {
                        if (headerImg) {
                            headerImg.src = '';
                            headerImg.classList.add('hidden');
                        }
                        if (headerInitial) {
                            headerInitial.textContent = (data.user.name || 'G').charAt(0).toUpperCase();
                            headerInitial.classList.remove('hidden');
                        }
                    }

                    // Reset removal flag
                    const removeFlag = document.getElementById('remove-avatar-flag');
                    if (removeFlag) removeFlag.value = '0';

                    showToast('{{ __("Profile updated successfully!") }}');
                    setTimeout(() => {
                        closeProfileModal();
                        window.location.reload();
                    }, 800);
                } else {
                    alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                    alertEl.textContent = data.message || 'Failed to update profile.';
                    alertEl.classList.remove('hidden');
                }
            } catch (err) {
                alertEl.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/60 border border-rose-500/40 text-rose-300';
                alertEl.textContent = 'Network or validation error occurred.';
                alertEl.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Profile';
            }
        }

        function detectProfileGps() {
            if (!navigator.geolocation) {
                showToast('{{ __("Geolocation is not supported by your browser.") }}');
                return;
            }

            const btnText = document.getElementById('profile-detect-gps-text');
            if (btnText) btnText.textContent = '{{ __("Detecting...") }}';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const res = await fetch('{{ route("profile.gps") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lon })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            const locInput = document.getElementById('profile-input-location');
                            if (locInput) locInput.value = data.location || `${data.city}, ${data.country}`;
                            showToast(`✓ GPS Detected: ${data.city || ''}, ${data.country || ''}`);
                        } else {
                            showToast(data.message || 'Failed to detect GPS location.');
                        }
                    } catch (err) {
                        showToast('Error sending GPS data.');
                    } finally {
                        if (btnText) btnText.textContent = '{{ __("Detect GPS") }}';
                    }
                },
                (err) => {
                    let msg = 'Failed to detect GPS.';
                    if (err.code === 1) msg = 'Location permission denied in browser.';
                    else if (err.code === 2) msg = 'Position unavailable.';
                    else if (err.code === 3) msg = 'GPS acquisition timed out.';
                    showToast(msg);
                    if (btnText) btnText.textContent = '{{ __("Detect GPS") }}';
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        function generateChannelCode() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            let prefix = '';
            for (let i = 0; i < 4; i++) {
                prefix += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            const num = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('input-code').value = `${prefix}-${num}`;
        }

        function generatePasscode() {
            const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            let pin = '';
            for (let i = 0; i < 8; i++) {
                pin += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('input-passcode').value = pin;
        }

        // Global Map Instance Reference
        let adminMapInstance = null;
        let adminUserMarker = null;

        // Initialize Global Leaflet Map
        document.addEventListener('DOMContentLoaded', () => {
            const mapEl = document.getElementById('admin-map');
            if (!mapEl) return;

            adminMapInstance = L.map('admin-map', {
                zoomControl: true,
                attributionControl: false
            }).setView([25, 0], 2);

            const cartoKey = @json(config('services.carto.key'));
            const tileUrl = 'https://{s}.basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png' + (cartoKey ? '?key=' + encodeURIComponent(cartoKey) : '');

            L.tileLayer(tileUrl, {
                maxZoom: 19,
                subdomains: 'abcd',
            }).addTo(adminMapInstance);

            const markers = @json($mapMarkers ?? []);

            markers.forEach(m => {
                if (m.latitude && m.longitude) {
                    const isUser = !!m.is_user;
                    const isAdmin = m.role === 'admin';
                    const color = isAdmin ? '#a855f7' : (isUser ? '#06b6d4' : '#10b981');

                    const circle = L.circleMarker([m.latitude, m.longitude], {
                        color: color,
                        fillColor: color,
                        fillOpacity: isUser ? 0.85 : 0.6,
                        radius: isUser ? 8 : 6,
                        weight: isUser ? 3 : 2
                    }).addTo(adminMapInstance);

                    const avatarHtml = m.avatar_url 
                        ? `<img src="${m.avatar_url}" class="w-5 h-5 rounded-full object-cover border border-slate-600 inline-block mr-1.5" alt="">`
                        : `<span class="w-5 h-5 rounded-full bg-slate-800 border border-slate-700 text-slate-300 font-bold text-[9px] inline-flex items-center justify-center mr-1.5">${(m.alias || 'U').substring(0, 1).toUpperCase()}</span>`;

                    const roleBadge = isUser
                        ? `<span class="text-[9px] font-mono px-1.5 py-0.5 rounded ${isAdmin ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'}">${isAdmin ? 'COMMAND' : 'MEMBER'}</span>`
                        : `<span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">VISITOR</span>`;

                    circle.bindPopup(`
                        <div class="text-xs font-mono p-1 space-y-1">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-800 pb-1 mb-1">
                                <div class="flex items-center font-bold text-white">
                                    ${avatarHtml}
                                    <span class="truncate max-w-[120px]">${m.alias}</span>
                                </div>
                                ${roleBadge}
                            </div>
                            <div class="text-slate-300 font-medium flex items-center gap-1">
                                <span>${m.flag || '📍'}</span>
                                <span>${m.city || ''}, ${m.country || ''}</span>
                            </div>
                            <div class="text-[10px] text-slate-400">GPS: ${Number(m.latitude).toFixed(4)}, ${Number(m.longitude).toFixed(4)}</div>
                            ${m.room_code ? `<div class="text-emerald-400 text-[11px]">Channel: ${m.room_code}</div>` : ''}
                            <div class="text-slate-500 text-[10px]">${m.last_seen_human || 'Active'}</div>
                        </div>
                    `);
                }
            });
        });

        // Admin GPS Synchronization
        function syncAdminGps() {
            if (!navigator.geolocation) {
                showToast('{{ __("Geolocation is not supported by your browser.") }}');
                return;
            }

            const btn = document.getElementById('admin-gps-sync-btn');
            const btnText = document.getElementById('admin-gps-btn-text');
            if (btn) btn.disabled = true;
            if (btnText) btnText.textContent = '{{ __("LOCATING...") }}';

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const res = await fetch('{{ route("admin.gps") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lon })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            showToast(`✓ GPS Synced: ${data.city || 'Verified Location'}, ${data.country || ''}`);

                            if (adminMapInstance) {
                                adminMapInstance.flyTo([lat, lon], 12, { animate: true, duration: 1.5 });

                                if (adminUserMarker) {
                                    adminUserMarker.setLatLng([lat, lon]);
                                } else {
                                    adminUserMarker = L.circleMarker([lat, lon], {
                                        color: '#a855f7',
                                        fillColor: '#a855f7',
                                        fillOpacity: 0.9,
                                        radius: 10,
                                        weight: 3
                                    }).addTo(adminMapInstance);
                                }

                                adminUserMarker.bindPopup(`
                                    <div class="text-xs font-mono p-1">
                                        <div class="font-bold text-purple-300 flex items-center gap-1">
                                            <span>${data.flag || '📍'}</span>
                                            <span>${data.city || 'Command Center'}, ${data.country || 'HQ'}</span>
                                        </div>
                                        <div class="text-slate-400 mt-0.5">GPS: ${lat.toFixed(4)}, ${lon.toFixed(4)}</div>
                                        <div class="text-purple-400 text-[10px] mt-0.5">Admin Live Position</div>
                                    </div>
                                `).openPopup();
                            }

                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showToast(data.message || 'Failed to sync GPS coordinates.');
                        }
                    } catch (err) {
                        showToast('Error sending GPS telemetry to server.');
                    } finally {
                        if (btn) btn.disabled = false;
                        if (btnText) btnText.textContent = '{{ __("SYNC GPS") }}';
                    }
                },
                (err) => {
                    let msg = 'Failed to obtain GPS coordinates.';
                    if (err.code === 1) msg = 'Location access was denied in browser permissions.';
                    else if (err.code === 2) msg = 'Position unavailable.';
                    else if (err.code === 3) msg = 'Location request timed out.';
                    showToast(msg);
                    if (btn) btn.disabled = false;
                    if (btnText) btnText.textContent = '{{ __("SYNC GPS") }}';
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        function showToast(msg) {
            const toast = document.getElementById('admin-toast');
            const toastMsg = document.getElementById('admin-toast-msg');
            if (!toast || !toastMsg) return;
            toastMsg.textContent = msg;
            toast.classList.remove('translate-y-16', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                toast.classList.add('translate-y-16', 'opacity-0', 'pointer-events-none');
            }, 3000);
        }

        function fallbackCopy(text, callback) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-999999px';
                textarea.style.top = '-999999px';
                textarea.setAttribute('readonly', '');
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (successful) {
                    if (callback) callback();
                } else {
                    prompt('Copy to clipboard (Ctrl+C / Cmd+C):', text);
                }
            } catch (e) {
                prompt('Copy to clipboard (Ctrl+C / Cmd+C):', text);
            }
        }

        function copyText(text, btnElement = null, successMsg = '{{ __("Copied to clipboard!") }}') {
            function onCopied() {
                showToast(successMsg);
                if (btnElement) {
                    const originalHtml = btnElement.innerHTML;
                    btnElement.innerHTML = `
                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-emerald-400 font-semibold">{{ __('Copied!') }}</span>
                    `;
                    btnElement.classList.add('border-emerald-500/50', 'bg-emerald-950/40');
                    setTimeout(() => {
                        btnElement.innerHTML = originalHtml;
                        btnElement.classList.remove('border-emerald-500/50', 'bg-emerald-950/40');
                    }, 2000);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text)
                    .then(onCopied)
                    .catch(() => fallbackCopy(text, onCopied));
            } else {
                fallbackCopy(text, onCopied);
            }
        }

        function copyChannelLink(roomCode, btnElement = null) {
            const url = window.location.origin + `/c/${encodeURIComponent(roomCode)}`;
            copyText(url, btnElement, `{{ __('Channel link copied!') }} (${url})`);
        }

        function copyFullInvite(roomCode, roomTitle, pin, btnElement = null) {
            const url = window.location.origin + `/c/${encodeURIComponent(roomCode)}`;
            const packageText = `Channel: ${roomTitle}\nLink: ${url}\nPIN: ${pin}`;
            copyText(packageText, btnElement, `{{ __('Link & PIN package copied!') }}`);
        }

        /* -------------------------------------------------------------
         * Biometrics & Passkeys (WebAuthn) Management
         * ------------------------------------------------------------- */
        function bufferDecode(value) {
            return Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
        }

        async function enrollCurrentDeviceBiometrics() {
            if (!window.PublicKeyCredential) {
                alert('{{ __("WebAuthn biometrics are not supported by this browser.") }}');
                return;
            }

            const btn = document.getElementById('enroll-bio-btn');
            const originalText = btn.textContent;
            btn.textContent = '{{ __("Awaiting Touch ID...") }}';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const optRes = await fetch('{{ route("webauthn.register.options") }}', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const options = await optRes.json();

                options.challenge = bufferDecode(options.challenge);
                options.user.id = bufferDecode(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(c => {
                        c.id = bufferDecode(c.id);
                        return c;
                    });
                }

                const credential = await navigator.credentials.create({ publicKey: options });
                if (!credential) {
                    throw new Error('Credential creation rejected');
                }

                const deviceLabel = prompt('{{ __("Enter a label for this device (e.g. MacBook Touch ID):") }}', 'Touch ID / Platform Key') || 'Touch ID Key';

                const payload = {
                    id: credential.id,
                    clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                    attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                    deviceName: deviceLabel,
                };

                const regRes = await fetch('{{ route("webauthn.register") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(payload)
                });

                const result = await regRes.json();
                if (result.success) {
                    showToast(result.message || '{{ __("Device registered successfully.") }}');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    alert(result.message || '{{ __("Biometric registration failed.") }}');
                }
            } catch (err) {
                console.error(err);
                if (err.name !== 'NotAllowedError') {
                    alert('{{ __("Biometric enrollment error: ") }}' + err.message);
                }
            } finally {
                btn.textContent = originalText;
            }
        }

        async function revokeBiometricKey(id, btnElement) {
            if (!confirm('{{ __("Are you sure you want to revoke this biometric passkey?") }}')) {
                return;
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/webauthn/credentials/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const result = await res.json();
                if (result.success) {
                    btnElement.closest('div').remove();
                    showToast('{{ __("Biometric passkey revoked.") }}');
                }
            } catch (err) {
                alert('{{ __("Failed to revoke biometric key.") }}');
            }
        }

        /* -------------------------------------------------------------
         * Two-Factor Authentication (TOTP) Handlers
         * ------------------------------------------------------------- */
        let currentRecoveryCodes = [];

        async function openTwoFactorSetupModal() {
            const modal = document.getElementById('two-factor-setup-modal');
            const step1 = document.getElementById('two-factor-step-1');
            const step2 = document.getElementById('two-factor-step-2');
            const qrWrap = document.getElementById('two-factor-qr-code-wrap');
            const secretCode = document.getElementById('two-factor-secret-code');
            const alertBox = document.getElementById('two-factor-setup-alert');

            alertBox.classList.add('hidden');
            step1.classList.remove('hidden');
            step2.classList.add('hidden');
            qrWrap.innerHTML = '<span class="text-slate-500 font-mono text-xs">{{ __("Generating QR Code...") }}</span>';
            modal.classList.remove('hidden');

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('{{ route("2fa.enable") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await res.json();

                if (data.success) {
                    secretCode.textContent = data.secret;
                    qrWrap.innerHTML = data.qr_code_svg;
                } else {
                    alert('{{ __("Failed to initialize 2FA setup.") }}');
                    closeTwoFactorSetupModal();
                }
            } catch (err) {
                alert('{{ __("Failed to contact server.") }}');
                closeTwoFactorSetupModal();
            }
        }

        function closeTwoFactorSetupModal() {
            document.getElementById('two-factor-setup-modal').classList.add('hidden');
        }

        async function submitConfirmTwoFactor() {
            const codeInput = document.getElementById('two-factor-confirm-code-input');
            const code = codeInput.value.trim();
            const alertBox = document.getElementById('two-factor-setup-alert');
            const btn = document.getElementById('submit-confirm-2fa-btn');

            if (code.length !== 6) {
                alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                alertBox.textContent = '{{ __("Please enter a 6-digit code.") }}';
                alertBox.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.textContent = '{{ __("Verifying...") }}';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('{{ route("2fa.confirm") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ code: code })
                });
                const data = await res.json();

                if (data.success) {
                    currentRecoveryCodes = data.recovery_codes || [];
                    const grid = document.getElementById('emergency-recovery-codes-grid');
                    grid.innerHTML = currentRecoveryCodes.map(c => `<div class="p-2 rounded bg-slate-900 border border-slate-800 tracking-wider">${c}</div>`).join('');

                    document.getElementById('two-factor-step-1').classList.add('hidden');
                    document.getElementById('two-factor-step-2').classList.remove('hidden');
                } else {
                    alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                    alertBox.textContent = data.message || '{{ __("Invalid verification code.") }}';
                    alertBox.classList.remove('hidden');
                }
            } catch (err) {
                alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                alertBox.textContent = '{{ __("Verification request failed.") }}';
                alertBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = '{{ __("Verify & Enable 2FA") }}';
            }
        }

        function copyRecoveryCodes() {
            if (currentRecoveryCodes.length === 0) return;
            const text = currentRecoveryCodes.join('\n');
            copyText(text, document.getElementById('copy-recovery-codes-btn'), '{{ __("Recovery codes copied to clipboard!") }}');
        }

        function finishTwoFactorSetup() {
            closeTwoFactorSetupModal();
            showToast('{{ __("Two-factor authentication active.") }}');
            setTimeout(() => window.location.reload(), 800);
        }

        function openDisable2faModal() {
            document.getElementById('disable-2fa-password').value = '';
            document.getElementById('disable-2fa-alert').classList.add('hidden');
            document.getElementById('two-factor-disable-modal').classList.remove('hidden');
        }

        function closeDisable2faModal() {
            document.getElementById('two-factor-disable-modal').classList.add('hidden');
        }

        async function submitDisable2fa() {
            const password = document.getElementById('disable-2fa-password').value;
            const alertBox = document.getElementById('disable-2fa-alert');
            const btn = document.getElementById('confirm-disable-2fa-btn');

            if (!password) {
                alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                alertBox.textContent = '{{ __("Please enter your current password.") }}';
                alertBox.classList.remove('hidden');
                return;
            }

            btn.disabled = true;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('{{ route("2fa.disable") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ password: password })
                });
                const data = await res.json();

                if (data.success) {
                    closeDisable2faModal();
                    showToast('{{ __("Two-factor authentication deactivated.") }}');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                    alertBox.textContent = data.message || '{{ __("Failed to disable 2FA.") }}';
                    alertBox.classList.remove('hidden');
                }
            } catch (err) {
                alertBox.className = 'p-2.5 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/30 text-rose-300';
                alertBox.textContent = '{{ __("Server request failed.") }}';
                alertBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
            }
        }

        /* -------------------------------------------------------------
         * Secondary Recovery Email Handler
         * ------------------------------------------------------------- */
        async function saveRecoveryEmail() {
            const input = document.getElementById('profile-recovery-email-input');
            const btn = document.getElementById('save-recovery-email-btn');
            const email = input.value.trim();

            if (!email) {
                alert('{{ __("Please enter a valid recovery email address.") }}');
                return;
            }

            btn.disabled = true;
            btn.textContent = '{{ __("Saving...") }}';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('{{ route("recovery.email.update") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ recovery_email: email })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message || '{{ __("Recovery email saved. Please confirm via link in inbox.") }}');
                } else {
                    alert(data.message || '{{ __("Failed to save recovery email.") }}');
                }
            } catch (err) {
                alert('{{ __("Request failed.") }}');
            } finally {
                btn.disabled = false;
                btn.textContent = '{{ __("Save") }}';
            }
        }
    
        // Executive Header Navigation & Dropdown Utilities
        function toggleAdminUserMenu() {
            const dropdown = document.getElementById("admin-user-dropdown");
            if (dropdown) {
                dropdown.classList.toggle("hidden");
            }
        }

        function toggleMobileAdminMenu() {
            const menu = document.getElementById("mobile-admin-menu");
            if (menu) {
                menu.classList.toggle("hidden");
            }
        }

        document.addEventListener("click", function(e) {
            const btn = document.getElementById("admin-user-menu-btn");
            const dropdown = document.getElementById("admin-user-dropdown");
            if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add("hidden");
            }
        });

    </script>
