@extends('layouts.app')

@section('title', $session->topic)

@php
    use App\Enums\AttendanceStatus;
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;

    $attending = ($tally[AttendanceStatus::Present->value] ?? 0) + ($tally[AttendanceStatus::Late->value] ?? 0);
    $expected = array_sum($tally);
@endphp

@section('content')
    <x-page-header :title="$session->topic" :subtitle="$session->humanSchedule()">
        <x-slot:actions>
            <a href="{{ route('sessions.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('sessions.qr', $session) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-qr-code me-1"></i> Kode QR
            </a>
            <a href="{{ route('attendances.edit', $session) }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-clipboard-check me-1"></i> Isi Presensi
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card label="Hadir" :value="$attending"
                         :meta="'dari ' . $expected . ' anggota'"
                         icon="person-check" color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Tingkat Kehadiran"
                         :value="NumberHelper::percentageValue($attending, $expected)"
                         :decimals="1" suffix="%"
                         icon="graph-up"
                         :color="NumberHelper::percentageValue($attending, $expected) >= 75 ? 'success' : 'warning'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Status" :value="$session->status->label()" :count-up="false"
                         :icon="$session->status->icon()" :color="$session->status->color()" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Presensi QR"
                         :value="$isCheckInOpen ? 'Terbuka' : 'Tertutup'"
                         :count-up="false"
                         icon="qr-code"
                         :color="$isCheckInOpen ? 'success' : 'secondary'"
                         :meta="$isCheckInOpen ? 'Anggota bisa scan sekarang' : 'Di luar jendela waktu'" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-3">Rincian</h2>

                    @foreach ([
                        ['Halaqah', $session->group->name, 'people'],
                        ['Mentor', $session->group->mentor?->name ?? '—', 'person-badge'],
                        ['Tanggal', DateHelper::formatLongDate($session->starts_at), 'calendar3'],
                        ['Waktu', DateHelper::formatTime($session->starts_at) . ' – ' . DateHelper::formatTime($session->ends_at), 'clock'],
                        ['Tempat', $session->location ?: '—', 'geo-alt'],
                    ] as [$rowLabel, $rowValue, $rowIcon])
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $rowIcon }} text-secondary"></i>
                            <span class="text-secondary" style="font-size:.8125rem;min-width:74px;">{{ $rowLabel }}</span>
                            <span class="fw-medium text-truncate" style="font-size:.875rem;">{{ $rowValue }}</span>
                        </div>
                    @endforeach

                    @if ($session->description)
                        <div class="pt-3">
                            <div class="text-secondary mb-1" style="font-size:.75rem;">Deskripsi</div>
                            <p class="mb-0" style="font-size:.875rem;">{{ $session->description }}</p>
                        </div>
                    @endif

                    @if ($session->summary)
                        <div class="pt-3 mt-3 border-top">
                            <div class="text-secondary mb-1" style="font-size:.75rem;">Ringkasan Hasil</div>
                            <p class="mb-0" style="font-size:.875rem;">{{ $session->summary }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-3">Rekap Presensi</h2>

                    @foreach (AttendanceStatus::cases() as $status)
                        @php $count = $tally[$status->value] ?? 0; @endphp
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $status->icon() }} text-{{ $status->color() }}"></i>
                            <span class="flex-grow-1" style="font-size:.875rem;">{{ $status->label() }}</span>
                            <span class="fw-bold text-tabular">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="120">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-0">Daftar Hadir</h2>
                            <p class="card-subtitle mb-0">Siapa saja yang hadir pada kegiatan ini</p>
                        </div>
                        <a href="{{ route('attendances.edit', $session) }}"
       wire:navigate class="btn btn-sm btn-light">
                            <i class="bi bi-pencil me-1"></i> Isi
                        </a>
                    </div>
                </div>

                @if ($attendances->isEmpty())
                    <x-empty-state
                        icon="clipboard-x"
                        title="Belum ada anggota"
                        text="Halaqah ini belum punya anggota, jadi daftar hadirnya kosong." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th>Cara</th>
                                    <th>Waktu Check-in</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendances as $attendance)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="d-grid rounded-circle bg-light text-secondary fw-bold flex-shrink-0"
                                                      style="width:28px;height:28px;place-items:center;font-size:.625rem;">
                                                    {{ $attendance->user->initials() }}
                                                </span>
                                                <span style="font-size:.875rem;">{{ $attendance->user->name }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $attendance->status->badgeClass() }}">
                                                {{ $attendance->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-secondary" style="font-size:.8125rem;">
                                            {{ $attendance->method->label() }}
                                        </td>
                                        <td class="text-tabular text-secondary" style="font-size:.8125rem;">
                                            {{ $attendance->checked_in_at ? DateHelper::formatTime($attendance->checked_in_at) : '—' }}
                                        </td>
                                        <td class="text-secondary text-truncate" style="font-size:.8125rem;max-width:160px;">
                                            {{ $attendance->note ?: '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
