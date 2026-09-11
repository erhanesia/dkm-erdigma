@extends('layouts.public')

@section('title', 'Jadwal Sholat')
@section('description', 'Jadwal sholat satu bulan penuh untuk '.$mosqueAddress.', dihitung dengan metode Kemenag RI.')

@section('content')

    {{-- ------------------------------------------------------- Header --}}
    <x-portal-hero>

        <div class="container position-relative">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-6" data-aos>
                    <span class="landing-eyebrow">
                        <i class="bi bi-calendar3 me-1"></i> Jadwal lengkap
                    </span>

                    <x-display-title class="mt-3">Jadwal Sholat</x-display-title>

                    <p class="landing-lead">
                        Dihitung secara astronomis dengan metode Kemenag RI untuk
                        koordinat masjid, lalu dicocokkan dengan jadwal resmi
                        daerah setempat.
                    </p>
                </div>

                <div class="col-lg-6" data-aos data-aos-delay="120">
                    @include('partials.portal.next-prayer', ['schedule' => $today])
                </div>
            </div>
        </div>
    </x-portal-hero>

    {{-- --------------------------------------------------- Hari ini --}}
    <section class="landing-section pb-0">
        <div class="container">
            <div class="text-center mb-4" data-aos>
                <h2 class="landing-heading h4">Hari Ini</h2>
                <p class="landing-sub">
                    {{ \App\Support\Helpers\DateHelper::formatLongDate($today->date) }}
                </p>
            </div>

            @include('partials.portal.prayer-strip', ['timings' => $todayTimings])
        </div>
    </section>

    {{-- ------------------------------------------------ Tabel sebulan --}}
    <section class="landing-section">
        <div class="container">

            {{-- Region, month, and the table itself: the only things on this
                 page that change when a visitor looks somewhere else. --}}
            <livewire:public-prayer-month />

            <p class="text-center text-body-tertiary small mt-4 mb-0" data-aos>
                <i class="bi bi-info-circle me-1"></i>
                Waktu terbit bukan waktu sholat — ia menandai berakhirnya waktu Subuh.
            </p>
        </div>
    </section>

@endsection
