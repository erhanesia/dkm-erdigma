@extends('layouts.app')

@section('title', $session->topic)

@php
    use App\Enums\AttendanceStatus;
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;

    $attending = ($tally[AttendanceStatus::Present->value] ?? 0) + ($tally[AttendanceStatus::Late->value] ?? 0);
    $expected = array_sum($tally);

    /*
     * The chart waits for the session to start: until then every member still
     * carries the absent row seeded with it, and a ring of red would report
     * absences that have not happened.
     */
    $isCancelled = $session->status === \App\Enums\SessionStatus::Cancelled;
    $showsChart = $expected > 0 && ! $isCancelled && $session->hasStarted();
    $rate = NumberHelper::percentageValue($attending, $expected);

    // Five statuses and the total they add up to: six tiles, so the grid
    // closes as even rows — three by two, or two by three on a phone.
    $tallyTiles = [
        ...array_map(static fn (AttendanceStatus $status): array => [
            'label' => $status->label(),
            'icon' => $status->icon(),
            'color' => $status->color(),
            'count' => $tally[$status->value] ?? 0,
            'share' => $expected > 0 ? NumberHelper::percentageValue($tally[$status->value] ?? 0, $expected, 0) : null,
        ], AttendanceStatus::cases()),
        ['label' => 'Total', 'icon' => 'people', 'color' => 'secondary', 'count' => $expected, 'share' => null],
    ];

    $sharePayload = [
        'labels' => array_map(static fn (AttendanceStatus $status): string => $status->label(), AttendanceStatus::cases()),
        'datasets' => [[
            'label' => 'Presensi',
            'data' => array_map(static fn (AttendanceStatus $status): int => $tally[$status->value] ?? 0, AttendanceStatus::cases()),
            'colors' => array_map(static fn (AttendanceStatus $status): string => $status->color(), AttendanceStatus::cases()),
        ]],
    ];
@endphp

@section('content')
    <x-page-header :title="$session->topic" :subtitle="$session->humanSchedule()">
        <x-slot:actions>
            <a href="{{ route('sessions.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            @if ($session->status === \App\Enums\SessionStatus::Scheduled)
                <a href="{{ route('sessions.reschedule.edit', $session) }}"
                   wire:navigate class="btn btn-light">
                    <i class="bi bi-calendar2-week me-1"></i> Jadwal Ulang
                </a>
            @endif
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

                    @if ($session->isRescheduled())
                        <div class="d-flex align-items-start gap-3 py-2 border-bottom">
                            <i class="bi bi-arrow-repeat text-warning"></i>
                            <div style="font-size:.8125rem;">
                                <div class="fw-medium">Dijadwal ulang</div>
                                <div class="text-secondary">
                                    Semula {{ DateHelper::formatLongDate($session->rescheduled_from) }},
                                    {{ DateHelper::formatTime($session->rescheduled_from) }}
                                    @if ($session->reschedule_reason)
                                        &middot; {{ $session->reschedule_reason }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($session->series)
                        <div class="d-flex align-items-start gap-3 py-2 border-bottom">
                            <i class="bi bi-collection text-secondary"></i>
                            <div style="font-size:.8125rem;">
                                <div class="fw-medium">Kegiatan berulang</div>
                                <div class="text-secondary">
                                    {{ $session->series->describe() }}, sampai
                                    {{ DateHelper::formatDate($session->series->ends_on) }}
                                </div>
                            </div>
                        </div>
                    @endif

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

        </div>

        <div class="col-12 col-lg-8">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                {{-- `flex-grow-0`: in a card stretched to the row's height, the header
                     would otherwise swallow the spare room and push the list down. --}}
                <div class="card-body flex-grow-0 pb-3">
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

                {{-- The roll call at a glance, right above the names it sums up: the
                     share of each status in the ring, the counts beside it as a grid
                     that closes on the total they add up to. --}}
                <div class="px-4 py-4 border-top">
                    <div class="row g-4 align-items-center">
                        @unless ($isCancelled)
                            <div class="col-12 col-md-auto d-flex justify-content-center">
                                @if ($showsChart)
                                    {{-- The rate sits in the ring's hole, laid over the canvas. --}}
                                    <div class="position-relative" style="width:184px;height:184px;">
                                        <canvas data-chart="doughnut" data-chart-payload='@json($sharePayload)'
                                                role="img" aria-label="Komposisi status presensi kegiatan ini"></canvas>
                                        <div class="position-absolute top-0 start-0 w-100 h-100 d-grid text-center"
                                             style="place-items:center;pointer-events:none;">
                                            <div>
                                                <div class="fw-bold text-tabular lh-1" style="font-size:1.625rem;">{{ number_format($rate, 0, ',', '.') }}%</div>
                                                <div class="text-secondary mt-1" style="font-size:.75rem;">kehadiran</div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-grid rounded-circle text-center text-body-tertiary px-4"
                                         style="width:184px;height:184px;place-items:center;font-size:.75rem;border:2px dashed var(--bs-border-color);">
                                        <span><i class="bi bi-pie-chart d-block fs-4 mb-1"></i>Grafik muncul setelah kegiatan dimulai.</span>
                                    </div>
                                @endif
                            </div>
                        @endunless

                        {{-- Three columns where the card has the width for them. From lg to
                             xl the card shares its row with Rincian, so two columns of three
                             rows sit beside the ring instead of squeezing the labels. --}}
                        <div class="col">
                            <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-2 row-cols-xxl-3 g-2">
                                @foreach ($tallyTiles as $tile)
                                    <div class="col">
                                        <div class="h-100 rounded-3 border px-3 py-2" style="min-width:0;">
                                            <div class="d-flex align-items-center gap-2 text-secondary" style="font-size:.75rem;">
                                                <i class="bi bi-{{ $tile['icon'] }} text-{{ $tile['color'] }} flex-shrink-0"></i>
                                                <span class="text-truncate">{{ $tile['label'] }}</span>
                                            </div>
                                            <div class="d-flex align-items-baseline gap-2">
                                                <span class="fw-bold text-tabular" style="font-size:1.375rem;">{{ $tile['count'] }}</span>
                                                @if ($tile['share'] !== null)
                                                    <span class="text-body-tertiary text-tabular" style="font-size:.75rem;">{{ number_format($tile['share'], 0, ',', '.') }}%</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
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
