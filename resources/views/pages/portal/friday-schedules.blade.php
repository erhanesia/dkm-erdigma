@extends('layouts.public')

@section('title', 'Khutbah Jumat')
@section('description', 'Jadwal khatib, imam, dan muadzin sholat Jumat di '.$mosqueName.'.')

@section('content')

    {{-- ------------------------------------------------------- Header --}}
    <x-portal-hero>

        <div class="container position-relative">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-7" data-aos>
                    <span class="landing-eyebrow">
                        <i class="bi bi-person-video3 me-1"></i> Jadwal khutbah
                    </span>

                    <x-display-title class="mt-3">Khutbah Jumat</x-display-title>

                    <p class="landing-lead">
                        Khatib, imam, dan muadzin untuk pekan-pekan mendatang.
                        Diperbarui langsung oleh pengurus DKM.
                    </p>
                </div>

                {{-- The sermon begins around Dhuhr, so that is the time to show. --}}
                <div class="col-lg-5" data-aos data-aos-delay="120">
                    <div class="landing-next">
                        <span class="landing-next-label">Sholat Jumat dimulai sekitar</span>

                        <div class="landing-next-time mt-2">
                            {{ $next && $next->start_time
                                ? substr((string) $next->start_time, 0, 5)
                                : \App\Support\Helpers\PublicSchedule::time($todaySchedule, \App\Enums\PrayerName::Dhuhr) }}
                        </div>

                        @if ($next)
                            <div class="landing-next-countdown">
                                <span class="text-uppercase small fw-semibold opacity-75">Jumat terdekat</span>
                                <strong>{{ \App\Support\Helpers\DateHelper::formatDate($next->date) }}</strong>
                            </div>
                        @endif

                        <div class="landing-next-foot">
                            @if ($next?->location)
                                <i class="bi bi-geo-alt me-1"></i> {{ $next->location }}
                            @else
                                Waktu Dzuhur hari ini dijadikan acuan.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-portal-hero>

    {{-- ---------------------------------------------------- Daftar --}}
    <section class="landing-section">
        <div class="container">
            <div class="text-center mb-5" data-aos>
                <h2 class="landing-heading">Pekan Mendatang</h2>
                <p class="landing-sub">
                    {{ $schedules->count() }} jadwal telah diumumkan.
                </p>
            </div>

            @forelse ($schedules as $index => $friday)
                <div data-aos data-aos-delay="{{ min(($index + 1) * 60, 300) }}">
                    @include('partials.portal.friday-card', [
                        'friday' => $friday,
                        'isFeatured' => $loop->first,
                    ])
                </div>
            @empty
                <div class="landing-empty" data-aos>
                    <i class="bi bi-calendar-x"></i>
                    <p class="mb-0">Belum ada jadwal khutbah yang diumumkan.</p>
                </div>
            @endforelse
        </div>
    </section>

@endsection
