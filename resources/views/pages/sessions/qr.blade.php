@extends('layouts.app')

@section('title', 'Kode QR Presensi')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Kode QR Presensi"
        :subtitle="$session->topic . ' — ' . $session->humanSchedule()">
        <x-slot:actions>
            <a href="{{ route('sessions.show', $session) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 justify-content-center">
        <div class="col-12 col-lg-6">
            {{-- The printable poster. Everything else on the page is marked
                 no-print so a print gives just this card. --}}
            <div class="card text-center" data-aos="fade-up">
                <div class="card-body py-5">
                    <div class="mb-1 text-secondary" style="font-size:.8125rem;">
                        {{ $session->group->name }}
                    </div>
                    <h2 class="fw-bold mb-1" style="font-size:1.375rem;">{{ $session->topic }}</h2>
                    <p class="text-secondary mb-4" style="font-size:.9375rem;">
                        {{ $session->humanSchedule() }}
                        @if ($session->location) · {{ $session->location }} @endif
                    </p>

                    <div class="d-inline-block p-3 bg-white rounded-4 border mb-3">
                        {!! $qrSvg !!}
                    </div>

                    <p class="fw-semibold mb-1">Scan untuk mencatat kehadiran</p>
                    <p class="text-secondary mb-0" style="font-size:.8125rem;">
                        Pastikan Anda sudah masuk ke aplikasi terlebih dahulu.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4 no-print">
            <div class="card mb-3" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-2">Status</h2>

                    <div @class([
                            'alert py-2 px-3 mb-3',
                            'alert-success' => $isCheckInOpen,
                            'alert-secondary' => ! $isCheckInOpen,
                        ]) style="font-size:.875rem;">
                        <i @class([
                            'bi me-1',
                            'bi-check-circle' => $isCheckInOpen,
                            'bi-clock' => ! $isCheckInOpen,
                        ])></i>
                        {{ $isCheckInOpen ? 'Presensi sedang terbuka.' : 'Presensi sedang tertutup.' }}
                    </div>

                    <p class="text-secondary mb-2" style="font-size:.8125rem;line-height:1.7;">
                        Jendela presensi terbuka
                        <strong>{{ config('dkm.mentoring.check_in_opens_before') }} menit sebelum</strong>
                        kegiatan mulai, dan tertutup
                        <strong>{{ config('dkm.mentoring.check_in_closes_after') }} menit setelah</strong>
                        kegiatan selesai. Di luar itu kode ini tidak berfungsi, jadi
                        foto QR tidak bisa dipakai di lain hari.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Tautan Presensi</label>
                        <div class="d-flex gap-2" x-data="{ copied: false }">
                            <input type="text" readonly value="{{ $checkInUrl }}"
                                   class="form-control" style="font-size:.75rem;"
                                   x-ref="link" onclick="this.select()">
                            <button type="button" class="btn btn-light flex-shrink-0"
                                    @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)">
                                <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                            </button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('sessions.qr.rotate', $session) }}"
                          data-confirm="Kode QR yang sudah dicetak atau difoto tidak akan berlaku lagi."
                          data-confirm-title="Buat kode QR baru?"
                          data-confirm-button="Ya, buat baru">
                        @csrf
                        <button type="submit" class="btn btn-light w-100 text-warning-emphasis" data-submitting-label="Membuat kode…">
                            <i class="bi bi-arrow-repeat me-1"></i> Buat Kode Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
