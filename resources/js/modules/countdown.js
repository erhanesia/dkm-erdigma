/**
 * Live clock and countdown to the next prayer.
 *
 * Ticks against the server time sent with the page rather than the device
 * clock, because a room PC with a drifted clock would otherwise show a
 * countdown that disagrees with the adhan that actually plays.
 */
/** Handle for the running tick, so navigation can clear it. */
let tickHandle = null;

/**
 * Difference between the server's clock and this browser's, in milliseconds.
 *
 * Measured once per full page load and then kept, which is the whole point:
 *
 * The topbar is inside `@persist`, so `wire:navigate` leaves it in place rather
 * than replacing it — and with it, the `data-server-time` stamped there when the
 * tab was first opened. Recomputing the offset on every navigation therefore
 * measured against a timestamp that never moves, and the clock jumped back to
 * whatever time the tab was opened. Reloading looked like a fix only because it
 * re-stamped the attribute.
 *
 * An offset does not go stale the way a timestamp does, so measuring it once is
 * both correct and cheaper. `null` means "not measured yet".
 */
let clockOffset = null;

/**
 * Stops the clock before the page is swapped — otherwise every navigation would
 * leave another interval running against elements that no longer exist.
 *
 * The offset deliberately survives: it belongs to the tab, not to the page.
 */
export function stopCountdown() {
    if (tickHandle !== null) {
        window.clearInterval(tickHandle);
        tickHandle = null;
    }
}

export function initCountdown() {
    stopCountdown();

    const clock = document.querySelector('[data-clock]');
    const countdown = document.querySelector('[data-countdown-to]');

    if (!clock && !countdown) {
        return;
    }

    if (clockOffset === null) {
        const serverNow = clock?.dataset.serverTime ?? countdown?.dataset.serverTime;
        const parsed = serverNow ? new Date(serverNow).getTime() : Number.NaN;

        clockOffset = Number.isNaN(parsed) ? 0 : parsed - Date.now();
    }

    const offset = clockOffset;

    // Re-read every navigation: this one is an absolute moment, not a relative
    // measurement, and each page may be counting down to something different.
    const target = countdown ? new Date(countdown.dataset.countdownTo).getTime() : null;

    const pad = (value) => String(value).padStart(2, '0');

    const tick = () => {
        const now = Date.now() + offset;

        if (clock) {
            const date = new Date(now);
            clock.textContent = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
        }

        if (countdown && target) {
            const remaining = Math.max(0, Math.floor((target - now) / 1000));
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            countdown.textContent = hours > 0
                ? `${hours} jam ${pad(minutes)} menit`
                : `${pad(minutes)}:${pad(seconds)}`;

            // Once the moment arrives, refresh so the page shows the next prayer.
            if (remaining === 0) {
                window.setTimeout(() => window.location.reload(), 30_000);
            }
        }
    };

    tick();
    tickHandle = window.setInterval(tick, 1000);
}
