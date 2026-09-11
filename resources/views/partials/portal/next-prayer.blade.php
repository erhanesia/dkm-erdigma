{{--
    The live "next prayer" card.

    Shown on the front page and above the monthly table, so it lives here rather
    than being copied. The clock and the countdown are driven by
    resources/js/modules/countdown.js, which ticks against `data-server-time`
    instead of the visitor's own clock.

    Expects: $nextPrayer, $nextPrayerAt, $serverTime, $schedule
--}}
<div class="landing-next">
    <div class="d-flex align-items-center justify-content-between">
        <span class="landing-next-label">Waktu sholat berikutnya</span>
        <span class="landing-clock"
              data-clock
              data-server-time="{{ $serverTime->toIso8601String() }}">
            {{ $serverTime->format('H:i:s') }}
        </span>
    </div>

    <div class="landing-next-name">
        <i class="bi bi-{{ $nextPrayer->icon() }}"></i>
        {{ $nextPrayer->label() }}
    </div>

    <div class="landing-next-time">{{ $nextPrayerAt->format('H:i') }}</div>

    <div class="landing-next-countdown">
        <span class="text-uppercase small fw-semibold opacity-75">Menuju adzan</span>
        <strong data-countdown-to="{{ $nextPrayerAt->toIso8601String() }}"
                data-server-time="{{ $serverTime->toIso8601String() }}">—</strong>
    </div>

    <div class="landing-next-foot">
        {{ \App\Support\Helpers\DateHelper::formatLongDate($schedule->date) }}
    </div>
</div>
