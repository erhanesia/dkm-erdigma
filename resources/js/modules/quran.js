/**
 * The Qur'an pages: filtering the surah list, and playing single verses.
 *
 * Both are small enough that a framework would be more code than the behaviour,
 * and the pages are plain server-rendered HTML with no Livewire on the public
 * side to lean on anyway.
 */

let cleanup = null;

export function stopQuran() {
    if (cleanup) {
        cleanup();
        cleanup = null;
    }
}

/**
 * Filters the 114 surahs as you type.
 *
 * The whole list is already in the page — roughly 6 KB of names — so matching
 * happens locally. A request per keystroke would be slower than the filtering it
 * replaced, and would stop working the moment the network hiccuped.
 */
function initSearch() {
    const input = document.querySelector('[data-quran-search]');
    const list = document.querySelector('[data-quran-list]');

    if (!input || !list) {
        return null;
    }

    const items = [...list.querySelectorAll('[data-quran-item]')];
    const counter = document.querySelector('[data-quran-count]');
    const empty = document.querySelector('[data-quran-empty]');

    const filter = () => {
        const term = input.value.trim().toLowerCase();
        let shown = 0;

        items.forEach((item) => {
            const match = term === '' || item.dataset.search.includes(term);

            item.style.display = match ? '' : 'none';
            shown += match ? 1 : 0;
        });

        if (counter) {
            counter.textContent = term === ''
                ? `${items.length} surat`
                : `${shown} dari ${items.length} surat`;
        }

        if (empty) {
            empty.style.display = shown === 0 ? '' : 'none';
        }
    };

    input.addEventListener('input', filter);

    return () => input.removeEventListener('input', filter);
}

/**
 * Plays one verse at a time through a single docked player.
 *
 * A separate `<audio>` per verse is the obvious approach and the wrong one: tap
 * two verses and both recite at once. Moving one element sidesteps that
 * entirely rather than having to pause the others.
 */
function initPlayer() {
    const player = document.querySelector('[data-quran-player]');
    const audio = document.querySelector('[data-quran-audio]');

    if (!player || !audio) {
        return null;
    }

    const label = player.querySelector('[data-quran-player-ayah]');
    const stop = player.querySelector('[data-quran-stop]');

    const clearPlaying = () => {
        document
            .querySelectorAll('.quran-verse.is-playing')
            .forEach((verse) => verse.classList.remove('is-playing'));
    };

    const close = () => {
        audio.pause();
        audio.removeAttribute('src');
        player.hidden = true;
        clearPlaying();
    };

    const onClick = (event) => {
        const button = event.target.closest('[data-quran-play]');

        if (button) {
            const verse = button.closest('.quran-verse');

            clearPlaying();
            verse?.classList.add('is-playing');

            if (label) {
                label.textContent = `ayat ${verse?.querySelector('.quran-verse-number')?.textContent.trim() ?? ''}`;
            }

            player.hidden = false;
            audio.src = button.dataset.quranPlay;
            audio.play().catch(() => {
                // Autoplay refused, or the file is unreachable. The controls are
                // on screen either way, so the person can start it themselves.
            });

            return;
        }

        if (event.target.closest('[data-quran-stop]')) {
            close();
        }
    };

    document.addEventListener('click', onClick);
    audio.addEventListener('ended', clearPlaying);

    return () => {
        document.removeEventListener('click', onClick);
        audio.removeEventListener('ended', clearPlaying);
        stop?.removeEventListener('click', close);
        close();
    };
}

/**
 * Copies a verse — Arabic, transliteration and translation together, which is
 * what someone forwarding it to a group actually wants.
 */
function initCopy() {
    const onClick = async (event) => {
        const button = event.target.closest('[data-quran-copy]');

        if (!button) {
            return;
        }

        const verse = button.closest('.quran-verse');
        const text = [
            verse?.querySelector('[data-quran-arabic]')?.textContent.trim(),
            verse?.querySelector('[data-quran-latin]')?.textContent.trim(),
            verse?.querySelector('[data-quran-translation]')?.textContent.trim(),
            `(QS. ${document.title.replace(/ —.*$/, '')}: ${button.dataset.quranCopy})`,
        ].filter(Boolean).join('\n\n');

        try {
            await navigator.clipboard.writeText(text);

            const icon = button.querySelector('i');
            const original = icon?.className;

            button.classList.add('is-done');

            if (icon) {
                icon.className = 'bi bi-check-lg';
            }

            window.setTimeout(() => {
                button.classList.remove('is-done');

                if (icon && original) {
                    icon.className = original;
                }
            }, 1400);
        } catch {
            // Clipboard refused — an insecure context, or permission denied.
            // Nothing useful to say, and nothing broken.
        }
    };

    document.addEventListener('click', onClick);

    return () => document.removeEventListener('click', onClick);
}

export function initQuran() {
    stopQuran();

    const teardowns = [initSearch(), initPlayer(), initCopy()].filter(Boolean);

    if (teardowns.length === 0) {
        return;
    }

    cleanup = () => teardowns.forEach((fn) => fn());
}
