@extends('layouts.app')

@section('title', $track->title)

@php
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;
@endphp

@section('content')
    <x-page-header :title="$track->title" :subtitle="$track->reciter ?: 'Tanpa keterangan qari.'">
        <x-slot:actions>
            <a href="{{ route('audio-tracks.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('audio-tracks.edit', $track) }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-3">Rincian Berkas</h2>

                    @foreach ([
                        ['Jenis', $track->type->label(), 'tag'],
                        ['Qari / Sumber', $track->reciter ?: '—', 'person-voice'],
                        ['Durasi', NumberHelper::duration($track->duration_seconds), 'stopwatch'],
                        ['Ukuran', NumberHelper::fileSize($track->file_size), 'hdd'],
                        ['Tipe MIME', $track->mime_type ?: '—', 'filetype-mp3'],
                        ['Nama Asli', $track->original_name ?: '—', 'file-earmark'],
                        ['Diunggah oleh', $track->uploader?->name ?? 'Sistem', 'person'],
                        ['Tanggal', DateHelper::formatDateTime($track->created_at), 'calendar3'],
                    ] as [$rowLabel, $rowValue, $rowIcon])
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $rowIcon }} text-secondary"></i>
                            <span class="text-secondary" style="font-size:.8125rem;min-width:118px;">{{ $rowLabel }}</span>
                            <span class="fw-medium text-truncate" style="font-size:.875rem;">{{ $rowValue }}</span>
                        </div>
                    @endforeach

                    <div class="d-flex flex-wrap gap-1 pt-3">
                        @if ($track->is_default)
                            <span class="badge text-bg-warning">
                                <i class="bi bi-star-fill"></i> Default {{ $track->type->label() }}
                            </span>
                        @endif
                        <span class="badge {{ $track->is_active ? 'text-bg-success' : 'text-bg-light border' }}">
                            {{ $track->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-2">Dipakai Di Mana</h2>

                    @php
                        $usageCount = $track->zonePrayerSettingsAsAdhan()->count()
                            + $track->zonePrayerSettingsAsTarhim()->count()
                            + $track->zonePrayerSettingsAsIqamah()->count()
                            + $track->murottalSchedules()->count();
                    @endphp

                    @if ($usageCount === 0)
                        <p class="text-secondary mb-0" style="font-size:.875rem;">
                            Belum dipakai di jadwal mana pun. Audio ini aman untuk dihapus.
                        </p>
                    @else
                        <p class="text-secondary" style="font-size:.875rem;">
                            Dipakai pada <strong>{{ $usageCount }}</strong> pengaturan.
                            Ganti dulu audionya di sana sebelum menghapus berkas ini.
                        </p>

                        @foreach ([
                            ['Adzan', $track->zonePrayerSettingsAsAdhan()->count()],
                            ['Tarhim', $track->zonePrayerSettingsAsTarhim()->count()],
                            ['Iqamah', $track->zonePrayerSettingsAsIqamah()->count()],
                            ['Jadwal tilawah', $track->murottalSchedules()->count()],
                        ] as [$usageLabel, $usageTotal])
                            @if ($usageTotal > 0)
                                <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                    <span style="font-size:.875rem;">{{ $usageLabel }}</span>
                                    <span class="badge text-bg-light border">{{ $usageTotal }}</span>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
