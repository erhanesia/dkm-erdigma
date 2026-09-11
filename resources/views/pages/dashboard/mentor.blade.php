@extends('layouts.app')

@section('title', 'Dashboard')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Dashboard Mentor"
        subtitle="Ringkasan halaqah binaan Anda dan jadwal ibadah hari ini.">
        <x-slot:actions>
            <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            @include('partials.prayer-times-card', [
                'schedule' => $schedule,
                'nextPrayer' => $next_prayer,
                'hijriDate' => $hijri_date,
            ])
        </div>

        <div class="col-12 col-lg-8">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card" data-aos="fade-up">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <h2 class="card-title">Kehadiran Halaqah Binaan</h2>
                                    <p class="card-subtitle mb-0">6 bulan terakhir</p>
                                </div>
                                <span class="badge text-bg-primary bg-opacity-10 text-primary fs-6">
                                    {{ number_format($attendance_rate, 1, ',', '.') }}%
                                </span>
                            </div>

                            @php
                                $trendPayload = [
                                    'labels' => array_column($attendance_trend, 'month'),
                                    'datasets' => [[
                                        'label' => 'Tingkat kehadiran (%)',
                                        'data' => array_column($attendance_trend, 'rate'),
                                        'color' => 'primary',
                                    ]],
                                ];
                            @endphp

                            <div style="height: 220px;">
                                <canvas data-chart="line" data-chart-payload='@json($trendPayload)'></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card" data-aos="fade-up" data-aos-delay="60">
                        <div class="card-body pb-2">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <h2 class="card-title mb-0">Rekap Bulan Ini</h2>
                                <a href="{{ route('reports.attendance') }}"
       wire:navigate class="btn btn-sm btn-light">
                                    Laporan lengkap
                                </a>
                            </div>
                        </div>

                        @if (empty($group_recap))
                            <x-empty-state
                                icon="clipboard-data"
                                title="Belum ada data kehadiran"
                                text="Rekap akan muncul setelah ada kegiatan yang presensinya terisi." />
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Halaqah</th>
                                            <th class="text-center">Kegiatan</th>
                                            <th class="text-center">Hadir</th>
                                            <th style="width:140px;">Tingkat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($group_recap as $row)
                                            <tr>
                                                <td class="fw-semibold">{{ $row['group'] }}</td>
                                                <td class="text-center text-tabular">{{ $row['sessions'] }}</td>
                                                <td class="text-center text-tabular">
                                                    {{ $row['attended'] }}/{{ $row['expected'] }}
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1">
                                                            <div class="progress-bar bg-{{ $row['rate'] >= 75 ? 'success' : ($row['rate'] >= 50 ? 'warning' : 'danger') }}"
                                                                 style="width: {{ $row['rate'] }}%"></div>
                                                        </div>
                                                        <span class="text-tabular small fw-semibold"
                                                              style="min-width:42px;">
                                                            {{ number_format($row['rate'], 0) }}%
                                                        </span>
                                                    </div>
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
        </div>

        <div class="col-12 col-lg-8">
            <div class="card" data-aos="fade-up">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <h2 class="card-title mb-0">Kegiatan Mendatang</h2>
                        <a href="{{ route('sessions.index') }}"
       wire:navigate class="btn btn-sm btn-light">Semua</a>
                    </div>
                </div>

                @forelse ($upcoming_sessions as $session)
                    <a href="{{ route('sessions.show', $session) }}"
       wire:navigate
                       class="d-flex align-items-center gap-3 px-4 py-3 text-body text-decoration-none border-top">
                        <div class="text-center flex-shrink-0" style="width:48px;">
                            <div class="fw-bold lh-1 fs-5">{{ $session->starts_at->format('j') }}</div>
                            <div class="text-secondary text-uppercase" style="font-size:.6875rem;">
                                {{ DateHelper::monthName((int) $session->starts_at->format('n')) }}
                            </div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $session->topic }}</div>
                            <div class="text-secondary text-truncate" style="font-size:.8125rem;">
                                {{ $session->group->name }} ·
                                {{ DateHelper::formatTime($session->starts_at) }}–{{ DateHelper::formatTime($session->ends_at) }}
                                @if ($session->location) · {{ $session->location }} @endif
                            </div>
                        </div>
                        <span class="badge {{ $session->status->badgeClass() }}">
                            {{ $session->status->label() }}
                        </span>
                    </a>
                @empty
                    <x-empty-state
                        icon="calendar-event"
                        title="Belum ada kegiatan"
                        text="Jadwalkan kajian atau mentoring untuk halaqah binaan Anda.">
                        <x-slot:action>
                            <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
                            </a>
                        </x-slot:action>
                    </x-empty-state>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-3">Jum'at Terdekat</h2>

                    @if ($next_friday)
                        <div class="fw-semibold mb-2">{{ DateHelper::formatLongDate($next_friday->date) }}</div>

                        @foreach ([
                            ['Khatib', $next_friday->khatibName(), 'mic'],
                            ['Imam', $next_friday->imam?->name ?? 'Belum ditentukan', 'person'],
                            ['Muadzin', $next_friday->muadzin?->name ?? 'Belum ditentukan', 'megaphone'],
                        ] as [$roleLabel, $personName, $roleIcon])
                            <div class="d-flex align-items-center gap-2 py-1" style="font-size:.875rem;">
                                <i class="bi bi-{{ $roleIcon }} text-secondary"></i>
                                <span class="text-secondary" style="min-width:64px;">{{ $roleLabel }}</span>
                                <span class="fw-medium">{{ $personName }}</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-secondary mb-0" style="font-size:.875rem;">
                            Jadwal Jum'at terdekat belum diatur pengurus.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
