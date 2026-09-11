/**
 * Aligns the calculated schedule with the officially published one.
 *
 * The difference between the two is a couple of minutes of safety margin that
 * the Kemenag tables carry and the raw astronomy does not. Rather than have
 * someone guess that margin per prayer, this measures it and shows the
 * comparison — the offsets are only written after they have been seen.
 */

const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 350;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.success === false) {
        throw new Error(payload?.message ?? 'Permintaan gagal.');
    }

    return payload.data;
}

export function initCalibration() {
    const panel = document.getElementById('calibration');

    if (!panel) {
        return;
    }

    const search = panel.querySelector('[data-calibration-search]');
    const results = panel.querySelector('[data-calibration-results]');
    const hint = panel.querySelector('[data-calibration-hint]');
    const previewButton = panel.querySelector('[data-calibration-preview]');
    const resultBox = panel.querySelector('[data-calibration-result]');
    const rows = panel.querySelector('[data-calibration-rows]');
    const cityIdField = panel.querySelector('[data-calibration-city-id]');
    const cancelButton = panel.querySelector('[data-calibration-cancel]');

    let selectedCity = null;
    let debounce = null;

    const say = (message, tone = 'secondary') => {
        hint.className = `form-hint text-${tone}`;
        hint.innerHTML = message;
    };

    const selectCity = (city) => {
        selectedCity = city;
        search.value = city.label;
        results.hidden = true;
        previewButton.disabled = false;
        say(`Daerah dipilih: <strong>${city.label}</strong>. Klik <strong>Bandingkan</strong>.`, 'success');
    };

    // ---- City search -----------------------------------------------------

    search.addEventListener('input', () => {
        selectedCity = null;
        previewButton.disabled = true;
        resultBox.hidden = true;

        window.clearTimeout(debounce);

        const keyword = search.value.trim();

        if (keyword.length < MIN_QUERY_LENGTH) {
            results.hidden = true;
            say('Pilih daerah yang jadwalnya biasa dipakai di kantor.');

            return;
        }

        debounce = window.setTimeout(async () => {
            say('Mencari daerah…');

            try {
                const cities = await getJson(
                    `${panel.dataset.searchUrl}?q=${encodeURIComponent(keyword)}`,
                );

                if (cities.length === 0) {
                    results.hidden = true;
                    say('Daerah tidak ditemukan. Coba nama kabupaten atau kotanya saja.', 'danger');

                    return;
                }

                results.innerHTML = cities
                    .map((city) => `
                        <button type="button" class="list-group-item list-group-item-action"
                                data-city-id="${city.id}" data-city-label="${city.label}">
                            <i class="bi bi-geo-alt me-1 text-secondary"></i> ${city.label}
                        </button>`)
                    .join('');

                results.hidden = false;
                say(`${cities.length} daerah ditemukan. Pilih salah satu.`);
            } catch (error) {
                results.hidden = true;
                say(error.message, 'danger');
            }
        }, DEBOUNCE_MS);
    });

    results.addEventListener('click', (event) => {
        const option = event.target.closest('[data-city-id]');

        if (option) {
            selectCity({ id: option.dataset.cityId, label: option.dataset.cityLabel });
        }
    });

    // ---- Comparison ------------------------------------------------------

    previewButton.addEventListener('click', async () => {
        if (!selectedCity) {
            return;
        }

        const original = previewButton.innerHTML;

        previewButton.disabled = true;
        previewButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengambil…';
        say(`Mengambil jadwal resmi <strong>${selectedCity.label}</strong>…`);

        try {
            const preview = await getJson(
                `${panel.dataset.previewUrl}?city_id=${encodeURIComponent(selectedCity.id)}`,
            );

            rows.innerHTML = preview.rows
                .map((row) => {
                    const changed = row.offset !== row.current;
                    const sign = row.offset > 0 ? `+${row.offset}` : `${row.offset}`;

                    return `
                        <tr>
                            <td>${row.label}</td>
                            <td class="text-center text-tabular text-secondary">${row.local}</td>
                            <td class="text-center text-tabular fw-semibold">${row.official}</td>
                            <td class="text-center">
                                <span class="badge ${changed ? 'text-bg-primary' : 'text-bg-light border'}">
                                    ${sign} menit
                                </span>
                                ${changed ? `<div class="text-body-tertiary" style="font-size:.6875rem;">dari ${row.current}</div>` : ''}
                            </td>
                        </tr>`;
                })
                .join('');

            cityIdField.value = selectedCity.id;
            resultBox.hidden = false;

            say(
                `Dibandingkan dengan <strong>${preview.city ?? selectedCity.label}</strong> `
                + `selama ${preview.sampled_days} hari. Periksa dulu, lalu klik Terapkan.`,
                'success',
            );
        } catch (error) {
            resultBox.hidden = true;
            say(error.message, 'danger');
        } finally {
            previewButton.disabled = false;
            previewButton.innerHTML = original;
        }
    });

    cancelButton.addEventListener('click', () => {
        resultBox.hidden = true;
    });
}
