/**
 * Fills the mosque coordinates and address from the device's location.
 *
 * Prayer times are computed from latitude and longitude, and typing those by
 * hand means looking them up elsewhere and hoping the digits are right — a
 * transposed decimal shifts the schedule by minutes. This reads the position
 * from the device, then asks the server to describe it in words so whoever set
 * it can confirm the right place was picked.
 *
 * Accuracy is not a concern: one kilometre of east-west error moves solar time
 * by roughly two seconds, so even a coarse WiFi-based fix is far more precise
 * than the schedule needs.
 */

/** Metres of accuracy beyond which the reading is worth mentioning. */
const COARSE_ACCURACY_THRESHOLD = 5000;

/**
 * Chrome, Firefox and Safari each hide the permission somewhere different, and
 * Chrome's "quieter prompts" can swallow the dialog entirely, so the advice
 * names the exact control rather than saying "check your browser settings".
 */
function permissionInstructions() {
    const ua = navigator.userAgent;

    if (/Firefox/i.test(ua)) {
        return 'Klik ikon <strong>gembok</strong> di kiri address bar → <strong>Hapus izin sementara</strong> → muat ulang halaman ini.';
    }

    if (/Safari/i.test(ua) && !/Chrome/i.test(ua)) {
        return 'Buka menu <strong>Safari → Settings → Websites → Location</strong>, ubah situs ini jadi <strong>Allow</strong>, lalu muat ulang halaman.';
    }

    return `
        <span class="d-block">1. Lihat <strong>ikon pin lokasi di address bar</strong> (biasanya bertanda coret), klik lalu pilih <strong>Izinkan</strong>.</span>
        <span class="d-block">2. Atau buka <code>chrome://settings/content/location</code> dan pastikan situs ini tidak ada di daftar <strong>Not allowed</strong>.</span>`;
}

function friendlyError(error) {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return `Izin lokasi ditolak.<br>${permissionInstructions()}`;
        case error.POSITION_UNAVAILABLE:
            return 'Lokasi tidak bisa ditentukan. Pastikan layanan lokasi di perangkat aktif, lalu coba lagi.';
        case error.TIMEOUT:
            return 'Perangkat terlalu lama mencari lokasi. Coba lagi.';
        default:
            return 'Gagal membaca lokasi perangkat.';
    }
}

export function initGeolocation() {
    const button = document.querySelector('[data-detect-location]');

    if (!button) {
        return;
    }

    const latitudeInput = document.getElementById('field-prayer-latitude');
    const longitudeInput = document.getElementById('field-prayer-longitude');
    const elevationInput = document.getElementById('field-prayer-elevation');
    const addressInput = document.getElementById('field-mosque-address');
    const status = document.querySelector('[data-location-status]');
    const addressUrl = button.dataset.addressUrl;

    if (!latitudeInput || !longitudeInput) {
        return;
    }

    const say = (html, tone = 'secondary') => {
        if (status) {
            status.className = `form-hint text-${tone}`;
            status.innerHTML = html;
        }
    };

    /**
     * Included on every failure. "Izin ditolak", "halaman bukan HTTPS" and "the
     * page's own Permissions-Policy blocks it" look identical from the outside
     * but need different fixes, so the message states what was actually seen.
     */
    const diagnostics = (permissionState) => `
        <span class="d-block mt-2 text-body-tertiary" style="font-size:.75rem;">
            Diagnosa: alamat <code>${window.location.origin}</code> ·
            konteks aman: <code>${window.isSecureContext ? 'ya' : 'TIDAK'}</code> ·
            status izin: <code>${permissionState ?? 'tidak terbaca'}</code>
        </span>`;

    /**
     * Asks the server what the coordinates correspond to.
     *
     * Cosmetic: the schedule is computed from the numbers, so a failure here
     * leaves everything working and simply skips filling the address.
     */
    const fillAddress = async (latitude, longitude) => {
        if (!addressInput || !addressUrl) {
            return null;
        }

        try {
            const response = await fetch(
                `${addressUrl}?latitude=${latitude}&longitude=${longitude}`,
                { headers: { Accept: 'application/json' } },
            );

            const payload = await response.json();
            const address = payload?.data?.address;
            const city = payload?.data?.city;

            if (address) {
                addressInput.value = address;
                addressInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            /*
             * The kabupaten matters as much as the coordinates now.
             *
             * The published Kemenag schedule is the primary source, and it is
             * published per kabupaten — so filling in coordinates alone would
             * leave the times belonging to wherever the setting last pointed.
             */
            const cityField = document.getElementById('field-official-city');
            const cityNote = document.querySelector('[data-official-city-note]');

            if (cityField) {
                cityField.value = city?.id ?? '';
                cityField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (cityNote) {
                cityNote.innerHTML = city
                    ? `<i class="bi bi-patch-check-fill text-success me-1"></i> Jadwal resmi akan diambil dari <strong>${city.label}</strong>.`
                    : '<i class="bi bi-calculator me-1"></i> Daerah ini tidak ada di daftar jadwal resmi — jadwal akan dihitung dari koordinat.';
            }

            return { address: address ?? null, city: city ?? null };
        } catch {
            return null;
        }
    };

    const requestPosition = (permissionState = null) => {
        const original = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mencari lokasi…';
        say('Menunggu izin lokasi dari browser. Kalau muncul dialog, pilih <strong>Izinkan</strong>.');

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const { latitude, longitude, accuracy, altitude } = position.coords;

                // Six decimals is about 0.1 m — finer than needed, and tidy.
                latitudeInput.value = latitude.toFixed(6);
                longitudeInput.value = longitude.toFixed(6);

                if (altitude !== null && elevationInput) {
                    elevationInput.value = Math.round(altitude);
                }

                [latitudeInput, longitudeInput, elevationInput].forEach((input) => {
                    input?.dispatchEvent(new Event('input', { bubbles: true }));
                });

                const rounded = Math.round(accuracy);
                const accuracyNote = accuracy > COARSE_ACCURACY_THRESHOLD
                    ? `Perkiraannya cukup kasar (±${(rounded / 1000).toFixed(1)} km), tapi untuk jadwal sholat selisih sebesar itu hanya menggeser waktu beberapa detik.`
                    : `Akurasi ±${rounded} m.`;

                say(`Koordinat terisi. ${accuracyNote} Mencari nama tempatnya…`, 'success');

                const found = await fillAddress(latitudeInput.value, longitudeInput.value);

                say(
                    found?.address
                        ? `Terdeteksi: <strong>${found.address}</strong>. ${accuracyNote}`
                            + (found.city
                                ? ` Jadwal resmi <strong>${found.city.label}</strong> akan dipakai.`
                                : '')
                            + ' Klik <strong>Simpan</strong> untuk menerapkannya.'
                        : `Koordinat terisi. ${accuracyNote} Nama tempat tidak bisa diambil, tapi jadwal tetap dihitung dari koordinatnya. Klik <strong>Simpan</strong>.`,
                    'success',
                );

                button.disabled = false;
                button.innerHTML = original;
            },
            (error) => {
                say(friendlyError(error) + diagnostics(permissionState), 'danger');

                button.disabled = false;
                button.innerHTML = original;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    };

    button.addEventListener('click', async () => {
        if (!('geolocation' in navigator)) {
            say(`Browser ini tidak mendukung deteksi lokasi. Isi koordinat manual.${diagnostics(null)}`, 'danger');

            return;
        }

        /*
         * Geolocation needs a secure context. `localhost` counts as secure, but
         * a LAN address such as `http://192.168.1.10:8000` does not — easy to hit
         * by accident, so the message names the address it saw.
         */
        if (!window.isSecureContext) {
            say(
                '<strong>Browser memblokir deteksi lokasi karena halaman ini bukan HTTPS.</strong><br>'
                + 'Buka lewat <code>localhost</code> (dianggap aman), atau isi koordinat manual.'
                + diagnostics(null),
                'danger',
            );

            return;
        }

        let permissionState = null;

        if (navigator.permissions?.query) {
            try {
                ({ state: permissionState } = await navigator.permissions.query({ name: 'geolocation' }));
            } catch {
                // Permissions API unavailable; the request below still works.
            }
        }

        if (permissionState === 'denied') {
            say(
                '<strong>Izin lokasi sedang diblokir untuk situs ini.</strong><br>'
                + permissionInstructions()
                + diagnostics(permissionState),
                'danger',
            );

            return;
        }

        requestPosition(permissionState);
    });
}
