@php
    use App\Enums\PrayerName;
    use App\Support\Helpers\DateHelper;

    /** @var \App\Models\PrayerSchedule $schedule */
    /** @var array{prayer: PrayerName, at: \Carbon\CarbonImmutable, countdown_seconds: int} $nextPrayer */
@endphp

<div class="card h-100" data-aos="fade-up">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
                <h2 class="card-title">Jadwal Sholat Hari Ini</h2>
                <p class="card-subtitle mb-0">
                    {{ DateHelper::formatLongDate() }} · {{ $hijriDate }}
                </p>
            </div>
            <a href="{{ route('prayer-schedules.index') }}"
       wire:navigate
               class="btn btn-sm btn-light" title="Lihat jadwal sebulan">
                <i class="bi bi-calendar3"></i>
            </a>
        </div>

        {{-- Countdown: the single most-looked-at number on this page. --}}
        <div class="rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between"
             style="background: linear-gradient(120deg, #ecfdf7, #d1fae8);">
            <div>
                <div class="text-uppercase fw-bold text-primary"
                     style="font-size:.6875rem;letter-spacing:.08em;">
                    Menuju {{ $nextPrayer['prayer']->label() }}
                </div>
                <div class="fw-bold text-tabular lh-1 mt-1"
                     style="font-size:1.75rem;letter-spacing:-.03em;color:#0f766e;"
                     data-countdown-to="{{ $nextPrayer['at']->toIso8601String() }}"
                     data-server-time="{{ now()->toIso8601String() }}">
                    --:--
                </div>
            </div>
            <div class="text-end">
                <div class="text-secondary" style="font-size:.75rem;">Pukul</div>
                <div class="fw-bold fs-5 text-tabular" style="color:#0f766e;">
                    {{ $nextPrayer['at']->format('H:i') }}
                </div>
            </div>
        </div>

        <div class="d-flex flex-column gap-1">
            @foreach (PrayerName::cases() as $prayer)
                @php $isNext = $nextPrayer['prayer'] === $prayer; @endphp

                <div @class([
                        'd-flex align-items-center gap-3 px-3 py-2 rounded-3',
                        'bg-light' => $isNext,
                    ])>
                    <i class="bi bi-{{ $prayer->icon() }} text-{{ $prayer->color() }}"></i>
                    <span @class(['flex-grow-1', 'fw-semibold' => $isNext])>
                        {{ $prayer->label() }}
                        @unless ($prayer->hasAdhan())
                            <span class="text-body-tertiary" style="font-size:.75rem;">(bukan waktu sholat)</span>
                        @endunless
                    </span>
                    <span @class(['text-tabular', 'fw-bold' => $isNext, 'text-secondary' => ! $isNext])>
                        {{ $schedule->timeFor($prayer) }}
                    </span>
                </div>
            @endforeach
        </div>

        @if ($schedule->is_manual_override)
            <p class="form-hint mb-0 mt-2">
                <i class="bi bi-pencil-square"></i>
                Jadwal hari ini diatur manual, bukan hasil perhitungan otomatis.
            </p>
        @endif
    </div>
</div>
