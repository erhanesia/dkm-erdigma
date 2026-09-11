/**
 * Live preview of today's prayer times on the settings page.
 *
 * Changing the madhab moves Asr by about forty minutes, and before this the only
 * way to see that was to save — which meant committing a change in order to find
 * out whether you wanted it. Now the card follows the form.
 *
 * The astronomy stays on the server: reimplementing it in JavaScript would give
 * two implementations to keep in agreement, and they would not stay in
 * agreement.
 */

/** Fields that change the outcome. Anything else is left alone. */
const WATCHED = [
    'prayer[latitude]',
    'prayer[longitude]',
    'prayer[elevation]',
    'prayer[calculation_method]',
    'prayer[asr_method]',
];

/**
 * Typing a latitude fires an event per keystroke; without this the server would
 * be asked about every half-written number.
 */
const DEBOUNCE_MS = 350;

let cleanup = null;

export function stopSettingsPreview() {
    if (cleanup) {
        cleanup();
        cleanup = null;
    }
}

export function initSettingsPreview() {
    stopSettingsPreview();

    const form = document.querySelector('[data-settings-form]');
    const list = document.querySelector('[data-preview-list]');

    if (!form || !list) {
        return;
    }

    const url = form.dataset.previewUrl;
    const note = document.querySelector('[data-preview-note]');
    const spinner = document.querySelector('[data-preview-spinner]');
    const savedNote = note?.innerHTML ?? '';

    let timer = null;
    let inFlight = null;

    const parameters = () => {
        const data = new URLSearchParams();
        const value = (name) => form.querySelector(`[name="${name}"]`)?.value ?? '';

        data.set('latitude', value('prayer[latitude]'));
        data.set('longitude', value('prayer[longitude]'));
        data.set('elevation', value('prayer[elevation]'));
        data.set('calculation_method', value('prayer[calculation_method]'));
        data.set('asr_method', value('prayer[asr_method]'));

        // The per-prayer corrections are part of the result too.
        form.querySelectorAll('[name^="prayer[adjustment]"]').forEach((input) => {
            const prayer = input.name.match(/\[adjustment]\[(\w+)]/)?.[1];

            if (prayer) {
                data.set(`adjustment[${prayer}]`, input.value || '0');
            }
        });

        return data;
    };

    const refresh = async () => {
        // A slower earlier request must not overwrite a newer answer.
        inFlight?.abort();
        inFlight = new AbortController();

        if (spinner) {
            spinner.style.display = '';
        }

        try {
            const response = await fetch(`${url}?${parameters()}`, {
                headers: { Accept: 'application/json' },
                signal: inFlight.signal,
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const timings = payload?.data?.timings ?? {};

            Object.entries(timings).forEach(([prayer, time]) => {
                const cell = list.querySelector(`[data-preview-time="${prayer}"]`);

                if (cell && cell.textContent.trim() !== time) {
                    cell.textContent = time;
                    // A brief highlight, so a value that moved is noticed rather
                    // than quietly different.
                    cell.classList.add('is-changed');
                    window.setTimeout(() => cell.classList.remove('is-changed'), 900);
                }
            });

            /*
             * The card must say which source these numbers came from.
             *
             * With the official Kemenag schedule as the primary source, the
             * hisab and madhab settings do not move an official day at all —
             * they only decide what the fallback would give. Saying "preview of
             * unsaved settings" over official figures would be a lie the
             * dropdown appears to confirm.
             */
            if (note) {
                note.innerHTML = payload?.data?.source === 'official'
                    ? '<span class="badge text-bg-success me-1">Resmi</span>'
                        + 'Diambil dari jadwal resmi Kemenag. Metode hisab dan mazhab '
                        + '<strong>tidak mengubah angka ini</strong> — keduanya hanya dipakai '
                        + 'saat jadwal resmi tidak bisa diambil.'
                    : 'Pratinjau dari pengaturan yang <strong>belum disimpan</strong>. '
                        + 'Klik Simpan untuk menerapkannya.';
            }

            // What the settings being edited *would* produce, shown beside the
            // official figures so the dropdowns still visibly do something.
            const fallback = document.querySelector('[data-preview-fallback]');

            if (fallback) {
                const alt = payload?.data?.fallback;

                fallback.hidden = !alt;

                if (alt) {
                    Object.entries(alt).forEach(([prayer, time]) => {
                        const cell = fallback.querySelector(`[data-preview-fallback-time="${prayer}"]`);

                        if (cell) {
                            cell.textContent = time;
                        }
                    });
                }
            }
        } catch {
            // Aborted or offline. The card keeps the last good values rather
            // than blanking out.
        } finally {
            if (spinner) {
                spinner.style.display = 'none';
            }
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, DEBOUNCE_MS);
    };

    const inputs = [
        ...WATCHED.map((name) => form.querySelector(`[name="${name}"]`)),
        ...form.querySelectorAll('[name^="prayer[adjustment]"]'),
    ].filter(Boolean);

    inputs.forEach((input) => {
        input.addEventListener('change', schedule);
        input.addEventListener('input', schedule);
    });

    cleanup = () => {
        window.clearTimeout(timer);
        inFlight?.abort();

        inputs.forEach((input) => {
            input.removeEventListener('change', schedule);
            input.removeEventListener('input', schedule);
        });

        if (note) {
            note.innerHTML = savedNote;
        }
    };
}
