@extends('layouts.public')

@section('title', 'After Hours')
@section('description', 'Jadwal kegiatan after hours — kajian dan pembinaan setelah jam kerja di '.$mosqueName.'.')

@section('content')

    {{-- ------------------------------------------------------- Header --}}
    <x-portal-hero>

        <div class="container position-relative text-center" data-aos>
            <span class="landing-eyebrow">
                <i class="bi bi-people me-1"></i> Terbuka untuk umum
            </span>

            <x-display-title class="mt-3">After Hours</x-display-title>

            <p class="landing-lead mx-auto text-center">
                Kajian rutin dan kegiatan pembinaan setelah jam kerja.
                Jadwal di bawah ini sudah dipastikan oleh pengurus DKM.
            </p>
        </div>
    </x-portal-hero>

    {{-- ---------------------------------------------------- Daftar --}}
    <section class="landing-section">
        <div class="container">

            @if ($total === 0)
                <div class="landing-empty" data-aos>
                    <i class="bi bi-calendar-x"></i>
                    <p class="mb-1">Belum ada kegiatan yang diumumkan.</p>
                    <p class="small mb-0">Jadwal berikutnya akan muncul di halaman ini.</p>
                </div>
            @else
                <p class="text-center landing-sub mb-5" data-aos>
                    {{ $total }} kegiatan mendatang.
                </p>

                @foreach ($sessionsByDate as $date => $sessions)
                    @php($day = \App\Support\Helpers\DateHelper::toCarbon($date))

                    <div class="landing-day-group"
                         data-aos
                         data-aos-delay="{{ min(($loop->index + 1) * 60, 300) }}">

                        <div class="landing-day-head">
                            <span class="landing-day-badge">
                                <span class="landing-day-num">{{ $day->format('d') }}</span>
                                <span class="landing-day-mon">
                                    {{ \App\Support\Helpers\DateHelper::shortMonthName((int) $day->format('n')) }}
                                </span>
                            </span>

                            <div class="min-w-0">
                                <div class="landing-day-name">
                                    {{ \App\Support\Helpers\DateHelper::dayName($day) }}
                                </div>
                                <div class="landing-day-full">
                                    {{ \App\Support\Helpers\DateHelper::formatLongDate($day) }}
                                </div>
                            </div>

                            <span class="landing-day-count">
                                {{ $sessions->count() }} kegiatan
                            </span>
                        </div>

                        {{-- Indented under the day header and joined to it by a
                             rail, so a day with three sessions reads as one day. --}}
                        <div class="landing-day-body">
                            @foreach ($sessions as $session)
                                @include('partials.portal.session-card', ['session' => $session])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            <p class="text-center text-body-tertiary small mt-4 mb-0" data-aos>
                <i class="bi bi-info-circle me-1"></i>
                Daftar peserta dan catatan kehadiran tidak ditampilkan di halaman publik.
            </p>
        </div>
    </section>

@endsection
