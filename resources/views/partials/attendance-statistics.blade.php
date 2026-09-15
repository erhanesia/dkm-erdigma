@use('App\Enums\AttendanceStatus')
@use('App\Support\Helpers\DateHelper')

{{--
    One year of attendance as a card: statuses stacked month by month on the
    left, the year's share of each status and its attendance rate on the right.

    Shared by the halaqah page and After Hours Saya, so the same numbers always
    look the same wherever they are read.

    @param array{months: array<int, array<string, int>>, totals: array<string, int>, recorded: int, attended: int, rate: float} $statistics
    @param array<int, int> $years
    @param int $year
    @param string $title
    @param string $subtitle
    @param string $emptyText
    @param string $anchor   the card's id, which the year filter returns to
    @param bool $filter     false where another card on the page already has one
--}}
@php
    $filter ??= true;
    $statuses = AttendanceStatus::cases();

    $monthlyPayload = [
        'labels' => array_map(static fn (int $month): string => DateHelper::shortMonthName($month), range(1, 12)),
        'stacked' => true,
        'datasets' => array_map(static fn (AttendanceStatus $status): array => [
            'label' => $status->label(),
            'data' => array_values(array_map(static fn (array $counts): int => $counts[$status->value], $statistics['months'])),
            'color' => $status->color(),
        ], $statuses),
    ];

    $sharePayload = [
        'labels' => array_map(static fn (AttendanceStatus $status): string => $status->label(), $statuses),
        'datasets' => [[
            'label' => 'Presensi',
            'data' => array_map(static fn (AttendanceStatus $status): int => $statistics['totals'][$status->value], $statuses),
            'colors' => array_map(static fn (AttendanceStatus $status): string => $status->color(), $statuses),
        ]],
    ];
@endphp

<div class="card" id="{{ $anchor }}" data-aos="fade-up">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <h2 class="card-title">{{ $title }}</h2>
                <p class="card-subtitle mb-0">{{ $subtitle }}</p>
            </div>

            @if ($filter)
                {{-- Returns to this card rather than the top of the page. --}}
                <form method="GET" action="{{ url()->current() }}#{{ $anchor }}" class="d-flex align-items-center gap-2">
                    <label for="{{ $anchor }}-tahun" class="text-secondary mb-0" style="font-size:.8125rem;">Tahun</label>
                    <select id="{{ $anchor }}-tahun" name="tahun" class="form-select form-select-sm" style="width:auto;"
                            onchange="this.form.submit()">
                        @foreach ($years as $option)
                            <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-light">Terapkan</button></noscript>
                </form>
            @endif
        </div>

        @if ($statistics['recorded'] === 0)
            <x-empty-state
                icon="bar-chart"
                :title="'Belum ada data kehadiran di ' . $year"
                :text="$emptyText" />
        @else
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div style="height:260px;">
                        <canvas data-chart="bar" data-chart-payload='@json($monthlyPayload)'
                                role="img" aria-label="Status kehadiran per bulan tahun {{ $year }}"></canvas>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="flex-shrink-0" style="width:112px;height:112px;">
                            <canvas data-chart="doughnut" data-chart-payload='@json($sharePayload)'
                                    role="img" aria-label="Komposisi status kehadiran tahun {{ $year }}"></canvas>
                        </div>
                        <div>
                            <div class="text-secondary" style="font-size:.75rem;">Tingkat kehadiran</div>
                            <div class="fs-3 fw-bold text-tabular lh-sm">{{ number_format($statistics['rate'], 1, ',', '.') }}%</div>
                            <div class="text-body-tertiary" style="font-size:.75rem;">
                                Hadir {{ $statistics['attended'] }} dari {{ $statistics['recorded'] }} presensi
                            </div>
                        </div>
                    </div>

                    @foreach ($statuses as $status)
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $status->icon() }} text-{{ $status->color() }}"></i>
                            <span class="flex-grow-1" style="font-size:.875rem;">{{ $status->label() }}</span>
                            <span class="fw-bold text-tabular">{{ $statistics['totals'][$status->value] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
