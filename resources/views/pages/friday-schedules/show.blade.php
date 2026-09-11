@extends('layouts.app')

@section('title', "Jadwal Jum'at")

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        :title="DateHelper::formatLongDate($schedule->date)"
        :subtitle="$schedule->theme ?: 'Tema khutbah belum ditentukan.'">
        <x-slot:actions>
            <a href="{{ route('friday-schedules.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('friday-schedules.edit', $schedule) }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card" data-aos="fade-up">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="card-title mb-0">Petugas</h2>
                        <span class="badge {{ $schedule->status->badgeClass() }}">
                            {{ $schedule->status->label() }}
                        </span>
                    </div>

                    @foreach ([
                        ['Khatib', $schedule->khatibName(), $schedule->external_khatib_origin ?: $schedule->khatib?->jobTitle(), 'mic'],
                        ['Imam', $schedule->imam?->name ?? 'Belum ditentukan', $schedule->imam?->jobTitle(), 'person'],
                        ['Muadzin', $schedule->muadzin?->name ?? 'Belum ditentukan', $schedule->muadzin?->jobTitle(), 'megaphone'],
                    ] as [$roleLabel, $personName, $personMeta, $roleIcon])
                        <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                            <span class="d-grid rounded-3 bg-light text-primary flex-shrink-0"
                                  style="width:40px;height:40px;place-items:center;">
                                <i class="bi bi-{{ $roleIcon }}"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="text-secondary" style="font-size:.75rem;">{{ $roleLabel }}</div>
                                <div class="fw-semibold text-truncate">{{ $personName }}</div>
                                @if ($personMeta)
                                    <div class="text-body-tertiary text-truncate" style="font-size:.75rem;">
                                        {{ $personMeta }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if ($schedule->notes)
                        <div class="pt-3">
                            <div class="text-secondary mb-1" style="font-size:.75rem;">Catatan</div>
                            <p class="mb-0" style="font-size:.9375rem;">{{ $schedule->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-3">Rincian</h2>

                    @foreach ([
                        ['Tanggal', DateHelper::formatLongDate($schedule->date), 'calendar3'],
                        ['Hijriah', DateHelper::formatHijri($schedule->date), 'moon-stars'],
                        ['Waktu', $schedule->start_time ? substr($schedule->start_time, 0, 5) : '—', 'clock'],
                        ['Lokasi', $schedule->location ?: '—', 'geo-alt'],
                    ] as [$rowLabel, $rowValue, $rowIcon])
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $rowIcon }} text-secondary"></i>
                            <span class="text-secondary" style="font-size:.8125rem;min-width:84px;">{{ $rowLabel }}</span>
                            <span class="fw-medium" style="font-size:.875rem;">{{ $rowValue }}</span>
                        </div>
                    @endforeach

                    @if ($schedule->missingRoles())
                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.8125rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Belum lengkap: {{ implode(', ', $schedule->missingRoles()) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
