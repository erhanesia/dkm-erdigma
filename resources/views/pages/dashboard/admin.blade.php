@extends('layouts.app')

@section('title', 'Dashboard')

@php
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;

    $successRate = $playback_summary['success_rate'];
    $problemsToday = $playback_today_problems;
@endphp

@section('content')
    <x-page-header
        title="Dashboard"
        :subtitle="'Ringkasan kondisi audio, jadwal, dan kegiatan per ' . DateHelper::formatDate() . '.'">
        <x-slot:actions>
            <a href="{{ route('playback-logs.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-activity me-1"></i> Monitoring
            </a>
            <a href="{{ route('audio-zones.index') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-speaker me-1"></i> Kelola Zona
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- The alert that answers the original complaint, shown before anything else. --}}
    @if ($playback_issues->isNotEmpty())
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">Ada {{ $playback_issues->count() }} pemutaran audio yang bermasalah</div>
                <div style="font-size:.875rem;">
                    Sebagian adzan tidak terdengar atau tidak dilaporkan perangkat. Periksa agar tidak terulang.
                </div>
            </div>
            <a href="{{ route('playback-logs.index') }}"
       wire:navigate class="btn btn-sm btn-warning fw-semibold">
                Lihat detail
            </a>
        </div>
    @endif

    @if (! empty($unscheduled_fridays))
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-3 mb-4" data-aos="fade-up">
            <i class="bi bi-calendar-x fs-4"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ count($unscheduled_fridays) }} Jum'at belum ada jadwal petugas</div>
                <div style="font-size:.875rem;">
                    Terdekat: {{ DateHelper::formatDate($unscheduled_fridays[0]) }}
                </div>
            </div>
            <a href="{{ route('friday-schedules.create', ['date' => $unscheduled_fridays[0]]) }}"
       wire:navigate
               class="btn btn-sm btn-info text-white fw-semibold">
                Atur sekarang
            </a>
        </div>
    @endif

    {{-- Headline metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3" data-aos="fade-up">
            <x-stat-card
                label="Adzan Berhasil (30 hari)"
                :value="$successRate"
                :decimals="1"
                suffix="%"
                icon="check2-circle"
                :color="$successRate >= 95 ? 'success' : ($successRate >= 85 ? 'warning' : 'danger')"
                :meta="$playback_summary['played'] . ' dari ' . $playback_summary['total'] . ' terputar'"
                :href="route('playback-logs.index')" />
        </div>

        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="60">
            <x-stat-card
                label="Masalah Hari Ini"
                :value="$problemsToday"
                icon="exclamation-triangle"
                :color="$problemsToday === 0 ? 'success' : 'danger'"
                :meta="$problemsToday === 0 ? 'Semua berjalan normal' : 'Perlu ditindaklanjuti'"
                :href="route('playback-logs.index', ['filter[problems_only]' => 1])" />
        </div>

        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="120">
            <x-stat-card
                label="Perangkat Online"
                :value="$device_health['online'] . ' / ' . $device_health['total']"
                :count-up="false"
                icon="pc-display"
                :color="$device_health['offline'] === 0 ? 'success' : 'warning'"
                :meta="$device_health['offline'] === 0 ? 'Semua terhubung' : $device_health['offline'] . ' perangkat offline'"
                :href="route('devices.index')" />
        </div>

        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="180">
            <x-stat-card
                label="Kehadiran Bulan Ini"
                :value="$attendance_rate"
                :decimals="1"
                suffix="%"
                icon="people"
                color="info"
                :meta="$sessions_this_month . ' kegiatan after hours'"
                :href="route('reports.attendance')" />
        </div>
    </div>

    <div class="row g-3">
        {{-- Prayer times --}}
        <div class="col-12 col-xl-4">
            @include('partials.prayer-times-card', [
                'schedule' => $schedule,
                'nextPrayer' => $next_prayer,
                'hijriDate' => $hijri_date,
            ])
        </div>

        {{-- Playback reliability chart --}}
        <div class="col-12 col-xl-8">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div>
                            <h2 class="card-title">Keandalan Pemutaran Audio</h2>
                            <p class="card-subtitle mb-0">14 hari terakhir</p>
                        </div>
                    </div>

                    @php
                        $trendPayload = [
                            'labels' => array_map(
                                fn ($row) => \Carbon\CarbonImmutable::parse($row['date'])->format('j/n'),
                                $playback_trend,
                            ),
                            'stacked' => true,
                            'datasets' => [
                                [
                                    'label' => 'Terputar',
                                    'data' => array_column($playback_trend, 'played'),
                                    'color' => 'success',
                                ],
                                [
                                    'label' => 'Tidak terdengar',
                                    'data' => array_column($playback_trend, 'silent'),
                                    'color' => 'warning',
                                ],
                                [
                                    'label' => 'Terlewat / gagal',
                                    'data' => array_map(
                                        fn ($row) => $row['missed'] + $row['failed'],
                                        $playback_trend,
                                    ),
                                    'color' => 'danger',
                                ],
                            ],
                        ];
                    @endphp

                    <div style="height: 260px;">
                        <canvas data-chart="bar" data-chart-payload='@json($trendPayload)'></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Zones --}}
        <div class="col-12 col-lg-7">
            <div class="card h-100" data-aos="fade-up">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div>
                            <h2 class="card-title">Zona Audio</h2>
                            <p class="card-subtitle mb-0">Status perangkat dan pengaturan per ruangan</p>
                        </div>
                        <a href="{{ route('audio-zones.index') }}"
       wire:navigate class="btn btn-sm btn-light">Semua</a>
                    </div>
                </div>

                @if ($zones->isEmpty())
                    <x-empty-state
                        icon="speaker"
                        title="Belum ada zona audio"
                        text="Tambahkan ruangan yang punya speaker agar adzan bisa dijadwalkan." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ruangan</th>
                                    <th class="text-center">Adzan</th>
                                    <th class="text-center">Tilawah</th>
                                    <th class="text-center">Perangkat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zones as $zone)
                                    <tr>
                                        <td>
                                            <a href="{{ route('audio-zones.show', $zone) }}"
       wire:navigate
                                               class="fw-semibold text-body">{{ $zone->name }}</a>
                                            @if ($zone->floor)
                                                <div class="text-body-tertiary" style="font-size:.75rem;">
                                                    {{ $zone->floor }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <i @class([
                                                'bi',
                                                'bi-check-circle-fill text-success' => $zone->is_adhan_enabled,
                                                'bi-x-circle text-body-tertiary' => ! $zone->is_adhan_enabled,
                                            ])></i>
                                        </td>
                                        <td class="text-center">
                                            <i @class([
                                                'bi',
                                                'bi-check-circle-fill text-success' => $zone->is_murottal_enabled,
                                                'bi-x-circle text-body-tertiary' => ! $zone->is_murottal_enabled,
                                            ])></i>
                                        </td>
                                        <td class="text-center">
                                            @if ($zone->devices_count === 0)
                                                <span class="badge text-bg-light border">belum ada</span>
                                            @else
                                                <span class="d-inline-flex align-items-center gap-2">
                                                    <span @class([
                                                        'status-dot',
                                                        'status-dot--live' => $zone->online_devices_count > 0,
                                                        'status-dot--offline' => $zone->online_devices_count === 0,
                                                    ])></span>
                                                    <span class="text-tabular" style="font-size:.8125rem;">
                                                        {{ $zone->online_devices_count }}/{{ $zone->devices_count }}
                                                    </span>
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Friday + sessions --}}
        <div class="col-12 col-lg-5">
            <div class="d-flex flex-column gap-3 h-100">
                <div class="card" data-aos="fade-up" data-aos-delay="60">
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

                            @if ($next_friday->theme)
                                <div class="mt-2 pt-2 border-top" style="font-size:.875rem;">
                                    <span class="text-secondary">Tema:</span> {{ $next_friday->theme }}
                                </div>
                            @endif

                            @if ($next_friday->missingRoles())
                                <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.8125rem;">
                                    Belum lengkap: {{ implode(', ', $next_friday->missingRoles()) }}
                                </div>
                            @endif
                        @else
                            <x-empty-state
                                icon="calendar-week"
                                title="Belum ada jadwal"
                                text="Jadwal Jum'at terdekat belum diatur." />
                        @endif
                    </div>
                </div>

                <div class="card flex-grow-1" data-aos="fade-up" data-aos-delay="120">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <h2 class="card-title mb-0">Kegiatan Mendatang</h2>
                            <a href="{{ route('sessions.index') }}"
       wire:navigate class="btn btn-sm btn-light">Semua</a>
                        </div>

                        @forelse ($upcoming_sessions as $session)
                            <a href="{{ route('sessions.show', $session) }}"
       wire:navigate
                               class="d-flex gap-3 py-2 text-body text-decoration-none border-bottom">
                                <div class="text-center flex-shrink-0" style="width:42px;">
                                    <div class="fw-bold lh-1" style="font-size:1.125rem;">
                                        {{ $session->starts_at->format('j') }}
                                    </div>
                                    <div class="text-secondary text-uppercase" style="font-size:.6875rem;">
                                        {{ DateHelper::monthName((int) $session->starts_at->format('n')) }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate" style="font-size:.875rem;">
                                        {{ $session->topic }}
                                    </div>
                                    <div class="text-secondary text-truncate" style="font-size:.75rem;">
                                        {{ $session->group->name }} ·
                                        {{ DateHelper::formatTime($session->starts_at) }}
                                    </div>
                                </div>
                            </a>
                        @empty
                            <x-empty-state
                                icon="calendar-event"
                                title="Tidak ada kegiatan"
                                text="Belum ada kegiatan after hours yang dijadwalkan." />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
