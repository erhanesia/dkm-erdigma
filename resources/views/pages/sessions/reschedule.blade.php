@extends('layouts.app')

@section('title', 'Jadwal Ulang')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        :title="'Jadwal Ulang: ' . $session->topic"
        :subtitle="'Saat ini ' . $session->humanSchedule() . '.'">
        <x-slot:actions>
            <a href="{{ route('sessions.show', $session) }}"
               wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('sessions.reschedule.update', $session) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="card-title mb-3">Jadwal Baru</h2>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-form.field name="starts_at" label="Mulai" type="datetime-local"
                                              :value="$session->starts_at->format('Y-m-d\TH:i')"
                                              required />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-form.field name="ends_at" label="Selesai" type="datetime-local"
                                              :value="$session->ends_at->format('Y-m-d\TH:i')"
                                              required />
                            </div>
                        </div>

                        <x-form.field name="reason" label="Alasan"
                                      placeholder="Contoh: Mentor berhalangan hadir"
                                      hint="Opsional. Tampil di rincian kegiatan di panel, tidak di halaman publik." />

                        @if ($session->series)
                            <div class="d-flex align-items-start justify-content-between py-2 border-top">
                                <div class="pe-3">
                                    <div class="fw-semibold" style="font-size:.875rem;">Terapkan juga ke kegiatan berikutnya</div>
                                    <div class="text-secondary" style="font-size:.75rem;">
                                        Kegiatan ini bagian dari seri {{ \Illuminate\Support\Str::lcfirst($session->series->describe()) }}.
                                        Kegiatan berikutnya digeser dengan selisih waktu yang sama &mdash; hanya yang
                                        masih terjadwal dan belum ada presensi hadir.
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0 flex-shrink-0">
                                    <input type="hidden" name="with_following" value="0">
                                    <input type="checkbox" name="with_following" value="1" class="form-check-input"
                                           @checked(old('with_following'))>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="card-title mb-3">Yang Tetap</h2>

                        <ul class="mb-0 ps-3 text-secondary" style="font-size:.875rem;">
                            <li class="mb-1">Daftar hadir dan catatan presensi tidak berubah.</li>
                            <li class="mb-1">Kode QR tetap sama; jendela presensinya mengikuti jadwal baru.</li>
                            @if ($session->isRescheduled())
                                <li>
                                    Waktu semula tetap tercatat:
                                    {{ DateHelper::formatLongDate($session->rescheduled_from) }},
                                    {{ DateHelper::formatTime($session->rescheduled_from) }}.
                                </li>
                            @else
                                <li>Waktu saat ini dicatat sebagai waktu semula dan ditampilkan di rincian kegiatan.</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-submitting-label="Menyimpan…">
                <i class="bi bi-check-lg me-1"></i> Simpan Jadwal Baru
            </button>
            <a href="{{ route('sessions.show', $session) }}"
               wire:navigate class="btn btn-light">Batal</a>
        </div>
    </form>
@endsection
