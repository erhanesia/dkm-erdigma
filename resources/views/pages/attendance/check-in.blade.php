@extends('layouts.app')

@section('title', 'Presensi')

@php
    use App\Support\Helpers\DateHelper;

    $alreadyCheckedIn = $existing?->status?->countsAsAttending() ?? false;
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card text-center animate-fade-up">
                <div class="card-body py-5">
                    @if ($alreadyCheckedIn)
                        <div class="d-inline-grid rounded-circle bg-success bg-opacity-10 text-success mb-3"
                             style="width:72px;height:72px;place-items:center;font-size:2rem;">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h1 class="fw-bold mb-1" style="font-size:1.375rem;">Presensi Tercatat</h1>
                        <p class="text-secondary mb-3">
                            Anda tercatat <strong>{{ $existing->status->label() }}</strong>
                            pada {{ DateHelper::formatTime($existing->checked_in_at) }}.
                        </p>
                    @elseif (! $isOpen)
                        <div class="d-inline-grid rounded-circle bg-secondary bg-opacity-10 text-secondary mb-3"
                             style="width:72px;height:72px;place-items:center;font-size:2rem;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h1 class="fw-bold mb-1" style="font-size:1.375rem;">Presensi Belum Dibuka</h1>
                        <p class="text-secondary mb-3">
                            Presensi hanya bisa diisi mulai
                            {{ config('dkm.mentoring.check_in_opens_before') }} menit sebelum kegiatan
                            sampai {{ config('dkm.mentoring.check_in_closes_after') }} menit setelahnya.
                        </p>
                    @else
                        <div class="d-inline-grid rounded-circle bg-primary bg-opacity-10 text-primary mb-3"
                             style="width:72px;height:72px;place-items:center;font-size:2rem;">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <h1 class="fw-bold mb-1" style="font-size:1.375rem;">Konfirmasi Kehadiran</h1>
                        <p class="text-secondary mb-3">
                            Tekan tombol di bawah untuk mencatat kehadiran Anda.
                        </p>
                    @endif

                    {{-- Session details, shown in every state so people can check
                         they scanned the right poster. --}}
                    <div class="bg-light rounded-3 p-3 text-start mb-4">
                        <div class="fw-bold mb-1">{{ $session->topic }}</div>
                        @if ($session->topic)
                            <div class="text-secondary mb-2" style="font-size:.875rem;">{{ $session->topic }}</div>
                        @endif

                        @foreach ([
                            ['calendar3', DateHelper::formatLongDate($session->starts_at)],
                            ['clock', DateHelper::formatTime($session->starts_at) . ' – ' . DateHelper::formatTime($session->ends_at)],
                            ['geo-alt', $session->location ?: 'Tempat belum ditentukan'],
                            ['person-badge', 'Mentor: ' . ($session->group->mentor?->name ?? '—')],
                        ] as [$rowIcon, $rowValue])
                            <div class="d-flex align-items-center gap-2 py-1" style="font-size:.8125rem;">
                                <i class="bi bi-{{ $rowIcon }} text-secondary"></i>
                                <span>{{ $rowValue }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($isOpen && ! $alreadyCheckedIn)
                        <form method="POST" action="{{ route('attendance.check-in.store', ['token' => $token]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-check-lg me-1"></i> Hadir Sekarang
                            </button>
                        </form>

                        @if ($session->isLateAt())
                            <p class="form-hint mt-2 mb-0">
                                Kegiatan sudah dimulai, jadi Anda akan tercatat sebagai
                                <strong>Terlambat</strong>.
                            </p>
                        @endif
                    @else
                        <a href="{{ route('dashboard') }}"
       wire:navigate class="btn btn-light w-100">
                            <i class="bi bi-house me-1"></i> Kembali ke Beranda
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
