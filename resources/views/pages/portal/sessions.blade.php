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

    {{-- --------------------------------------------------- Kalender --}}
    <section class="landing-section">
        <div class="container">

            {{-- The month picker, the calendar and the month's agenda: the only
                 part of this page that changes when a visitor browses. --}}
            <livewire:public-session-calendar />

            <p class="text-center text-body-tertiary small mt-4 mb-0" data-aos>
                <i class="bi bi-info-circle me-1"></i>
                Daftar peserta dan catatan kehadiran tidak ditampilkan di halaman publik.
            </p>
        </div>
    </section>

@endsection
