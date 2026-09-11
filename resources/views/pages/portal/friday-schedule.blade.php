@extends('layouts.public')

@section('title', 'Khutbah Jumat '.\App\Support\Helpers\DateHelper::formatDate($schedule->date))

@section('description', $schedule->theme
    ? $schedule->theme.' — khatib '.$schedule->khatibName().', '.\App\Support\Helpers\DateHelper::formatLongDate($schedule->date)
    : 'Khatib '.$schedule->khatibName().' — '.\App\Support\Helpers\DateHelper::formatLongDate($schedule->date))

@section('content')

    <x-portal-hero>

        <div class="container position-relative">
            {{-- Trail back to the list. A detail page reached from a shared link
                 has no history to go back through. --}}
            <a href="{{ route('portal.friday-schedules') }}" class="landing-back">
                <i class="bi bi-arrow-left me-1"></i> Semua jadwal Jumat
            </a>

            <span class="landing-eyebrow mt-3">
                <i class="bi bi-calendar-week me-1"></i>
                {{ \App\Support\Helpers\DateHelper::formatLongDate($schedule->date) }}
            </span>

            <x-display-title class="mt-3">
                {{ $schedule->theme ?: 'Khutbah Jumat' }}
            </x-display-title>

            <p class="landing-lead">
                Khatib <strong>{{ $schedule->khatibName() }}</strong>
                @if ($schedule->external_khatib_origin)
                    dari {{ $schedule->external_khatib_origin }}
                @endif
            </p>
        </div>
    </x-portal-hero>

    <section class="landing-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8" data-aos>
                    <div class="landing-detail">
                        <h2 class="landing-heading h4 mb-4">Petugas</h2>

                        <dl class="landing-facts">
                            <dt><i class="bi bi-person-video3"></i> Khatib</dt>
                            <dd>
                                {{ $schedule->khatibName() }}
                                @if ($schedule->external_khatib_origin)
                                    <span class="d-block text-body-tertiary" style="font-size:.8125rem;">
                                        {{ $schedule->external_khatib_origin }}
                                    </span>
                                @endif
                            </dd>

                            <dt><i class="bi bi-person"></i> Imam</dt>
                            <dd>{{ $schedule->imam?->name ?? 'Belum ditentukan' }}</dd>

                            <dt><i class="bi bi-megaphone"></i> Muadzin</dt>
                            <dd>{{ $schedule->muadzin?->name ?? 'Belum ditentukan' }}</dd>

                            <dt><i class="bi bi-clock"></i> Waktu</dt>
                            <dd>
                                {{ $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '11:45' }} WIB
                                <span class="d-block text-body-tertiary" style="font-size:.8125rem;">
                                    Dzuhur hari ini
                                    {{ \App\Support\Helpers\PublicSchedule::time($todaySchedule, \App\Enums\PrayerName::Dhuhr) }}
                                </span>
                            </dd>

                            <dt><i class="bi bi-geo-alt"></i> Tempat</dt>
                            <dd>{{ $schedule->location ?: 'Musholla Erdigma' }}</dd>
                        </dl>

                        @if ($schedule->notes)
                            <div class="landing-note mt-4">
                                <i class="bi bi-info-circle me-1"></i>
                                {{ $schedule->notes }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ------------------------------------------- Jumat lain --}}
                <div class="col-lg-4" data-aos data-aos-delay="120">
                    <div class="landing-aside">
                        <h2 class="landing-heading h5 mb-3">Jumat Berikutnya</h2>

                        @forelse ($others as $other)
                            <a href="{{ route('portal.friday-schedules.show', ['date' => $other->date->toDateString()]) }}"
                               class="landing-aside-item">
                                <span class="landing-aside-date">
                                    {{ $other->date->format('d') }}
                                    {{ \App\Support\Helpers\DateHelper::shortMonthName((int) $other->date->format('n')) }}
                                </span>
                                <span class="landing-aside-title">{{ $other->theme ?: 'Tema belum ditentukan' }}</span>
                                <span class="landing-aside-meta">{{ $other->khatibName() }}</span>
                            </a>
                        @empty
                            <p class="text-body-tertiary mb-0" style="font-size:.875rem;">
                                Belum ada jadwal berikutnya.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
