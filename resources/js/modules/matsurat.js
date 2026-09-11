/**
 * The Al-Ma'tsurat counter.
 *
 * This is the feature, not the text. The text is in every printed copy and on a
 * hundred websites; what a screen can do that paper cannot is remember that you
 * are on the sixty-third of a hundred when the phone rings.
 *
 * Progress lives in localStorage, keyed by reading and by date:
 *
 *   - localStorage, not the server, because it is nobody's business but the
 *     reader's, and because the page must work without an account.
 *   - keyed by date, because yesterday's tally means nothing today and a
 *     counter that has to be cleared by hand is a counter people stop trusting.
 */

/*
 * Versioned.
 *
 * Tallies are keyed by an item's position in the reading, so replacing the text
 * with one that has a different order would have made today's saved counts land
 * on the wrong supplications. Bumping the prefix retires them instead.
 */
const STORAGE_PREFIX = 'dkm.matsurat.v2';

/** Beyond this the dots become a smear; the tally carries the number instead. */
const MAX_DOTS = 10;

let cleanup = null;

function today() {
    // Local date, not UTC: "today" here means the reader's day, and in
    // Indonesia UTC would roll over at seven in the morning.
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Reads the saved tally, discarding anything from an earlier day.
 */
function load(key) {
    try {
        const raw = window.localStorage.getItem(`${STORAGE_PREFIX}.${key}`);

        if (!raw) {
            return {};
        }

        const saved = JSON.parse(raw);

        return saved?.date === today() ? (saved.counts ?? {}) : {};
    } catch {
        // Private mode, or storage disabled. The counter still works for this
        // visit; it simply will not survive a reload.
        return {};
    }
}

function save(key, counts) {
    try {
        window.localStorage.setItem(
            `${STORAGE_PREFIX}.${key}`,
            JSON.stringify({ date: today(), counts }),
        );
    } catch {
        // Nothing to do and nothing broken.
    }
}

export function stopMatsurat() {
    if (cleanup) {
        cleanup();
        cleanup = null;
    }
}

export function initMatsurat() {
    stopMatsurat();

    const bar = document.querySelector('[data-matsurat]');

    if (!bar) {
        return;
    }

    const { key, total } = bar.dataset;
    const totalReadings = Number(total) || 0;

    const fill = bar.querySelector('[data-matsurat-fill]');
    const doneLabel = bar.querySelector('[data-matsurat-done]');
    const progress = bar.querySelector('.matsurat-progress');
    const doneCard = document.querySelector('[data-matsurat-done-card]');
    const items = [...document.querySelectorAll('[data-matsurat-item]')];

    let counts = load(key);

    /** Repaints one item from the current tally. */
    const paintItem = (item) => {
        const id = item.dataset.matsuratItem;
        const repeat = Number(item.dataset.repeat) || 1;
        const done = Math.min(counts[id] ?? 0, repeat);

        item.classList.toggle('is-done', done >= repeat);

        const tally = item.querySelector('[data-matsurat-item-done]');

        if (tally) {
            tally.textContent = String(done);
        }

        const dots = item.querySelectorAll('[data-matsurat-dots] > span');

        dots.forEach((dot, index) => {
            // With more repetitions than dots, each dot stands for a share of
            // them — a hundred dots would be noise, not information.
            const perDot = repeat / dots.length;
            dot.classList.toggle('is-on', done >= (index + 1) * perDot);
        });

        const label = item.querySelector('[data-matsurat-tap-label]');

        if (label) {
            if (done >= repeat) {
                label.textContent = 'Selesai';
            } else if (repeat > 1) {
                label.textContent = `Sisa ${repeat - done}×`;
            }
        }
    };

    const paintTotal = () => {
        const done = items.reduce((sum, item) => {
            const repeat = Number(item.dataset.repeat) || 1;

            return sum + Math.min(counts[item.dataset.matsuratItem] ?? 0, repeat);
        }, 0);

        const percent = totalReadings === 0 ? 0 : (done / totalReadings) * 100;

        if (fill) {
            fill.style.width = `${percent}%`;
        }

        if (doneLabel) {
            doneLabel.textContent = String(done);
        }

        if (progress) {
            progress.setAttribute('aria-valuenow', String(done));
        }

        bar.classList.toggle('is-complete', done >= totalReadings && totalReadings > 0);

        if (doneCard) {
            doneCard.hidden = !(done >= totalReadings && totalReadings > 0);
        }
    };

    const paintAll = () => {
        items.forEach(paintItem);
        paintTotal();
    };

    const onClick = (event) => {
        if (event.target.closest('[data-matsurat-reset]')) {
            counts = {};
            save(key, counts);
            paintAll();

            return;
        }

        const tap = event.target.closest('[data-matsurat-tap]');

        if (!tap) {
            return;
        }

        const item = tap.closest('[data-matsurat-item]');
        const id = item.dataset.matsuratItem;
        const repeat = Number(item.dataset.repeat) || 1;
        const done = counts[id] ?? 0;

        // Tapping a finished item starts it over, so a miscount is one tap to
        // fix rather than a reset of the whole reading.
        counts[id] = done >= repeat ? 0 : done + 1;

        save(key, counts);
        paintItem(item);
        paintTotal();

        // A short pulse, so a tap that lands is felt as well as seen.
        tap.classList.add('is-tapped');
        window.setTimeout(() => tap.classList.remove('is-tapped'), 220);
    };

    document.addEventListener('click', onClick);
    paintAll();

    cleanup = () => document.removeEventListener('click', onClick);
}
