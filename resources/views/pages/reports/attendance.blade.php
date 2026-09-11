@extends('layouts.app')

@section('title', 'Laporan Kehadiran')

@php
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;

    $totalExpected = array_sum(array_column($byGroup, 'expected'));
    $totalAttended = array_sum(array_column($byGroup, 'attended'));
@endphp

@section('content')
    <x-page-header
        title="Laporan Kehadiran"
        :subtitle="'Periode ' . DateHelper::formatDate($from) . ' – ' . DateHelper::formatDate($to) . '.'">
        <x-slot:actions>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
            <a href="{{ route('reports.attendance.export', request()->query()) }}" class="btn btn-primary">
                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="row g-2 align-items-end mb-4 no-print">
        <div class="col-6 col-md-3">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary" data-submitting-label="Menerapkan…">
                <i class="bi bi-funnel me-1"></i> Terapkan
            </button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card label="Kegiatan" :value="$sessions->count()"
                         icon="calendar-event" color="primary" meta="dalam periode ini" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Total Kehadiran" :value="$totalAttended"
                         icon="person-check" color="success"
                         :meta="'dari ' . $totalExpected . ' yang diharapkan'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Tingkat Kehadiran"
                         :value="NumberHelper::percentageValue($totalAttended, $totalExpected)"
                         :decimals="1" suffix="%"
                         icon="graph-up"
                         :color="NumberHelper::percentageValue($totalAttended, $totalExpected) >= 75 ? 'success' : 'warning'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Halaqah Terlibat" :value="count($byGroup)"
                         icon="people" color="info" />
        </div>
    </div>

    <div class="card mb-4" data-aos="fade-up">
        <div class="card-body">
            <h2 class="card-title mb-1">Tren Kehadiran</h2>
            <p class="card-subtitle mb-3">6 bulan terakhir</p>

            @php
                $trendPayload = [
                    'labels' => array_column($trend, 'month'),
                    'datasets' => [[
                        'label' => 'Tingkat kehadiran (%)',
                        'data' => array_column($trend, 'rate'),
                        'color' => 'primary',
                    ]],
                ];
            @endphp

            <div style="height:240px;">
                <canvas data-chart="line" data-chart-payload='@json($trendPayload)'></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="table-card h-100" data-aos="fade-up">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-0">Rekap per Halaqah</h2>
                </div>

                @if (empty($byGroup))
                    <x-empty-state icon="people" title="Belum ada data"
                                   text="Tidak ada kegiatan pada periode ini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Halaqah</th>
                                    <th class="text-center">Sesi</th>
                                    <th class="text-center">Hadir</th>
                                    <th style="width:120px;">Tingkat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byGroup as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold" style="font-size:.875rem;">{{ $row['group'] }}</div>
                                            <div class="text-body-tertiary" style="font-size:.6875rem;">
                                                {{ $row['mentor'] }}
                                            </div>
                                        </td>
                                        <td class="text-center text-tabular">{{ $row['sessions'] }}</td>
                                        <td class="text-center text-tabular" style="font-size:.8125rem;">
                                            {{ $row['attended'] }}/{{ $row['expected'] }}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height:6px;">
                                                    <div class="progress-bar bg-{{ $row['rate'] >= 75 ? 'success' : ($row['rate'] >= 50 ? 'warning' : 'danger') }}"
                                                         style="width: {{ $row['rate'] }}%"></div>
                                                </div>
                                                <span class="text-tabular fw-semibold" style="font-size:.75rem;min-width:34px;">
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

        <div class="col-12 col-xl-7">
            <div class="table-card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-0">Rekap per Karyawan</h2>
                            <p class="card-subtitle mb-0">Diurutkan berdasarkan nama</p>
                        </div>
                    </div>
                </div>

                @if (empty($byMember))
                    <x-empty-state icon="person-lines-fill" title="Belum ada data"
                                   text="Rekap muncul setelah presensi kegiatan terisi." />
                @else
                    <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="position-sticky top-0 bg-white" style="z-index:1;">
                                <tr>
                                    <th>Nama</th>
                                    <th>Departemen</th>
                                    <th class="text-center">Hadir</th>
                                    <th style="width:120px;">Tingkat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byMember as $row)
                                    <tr>
                                        <td class="fw-medium" style="font-size:.875rem;">{{ $row['name'] }}</td>
                                        <td class="text-secondary" style="font-size:.8125rem;">
                                            {{ $row['department'] ?: '—' }}
                                        </td>
                                        <td class="text-center text-tabular" style="font-size:.8125rem;">
                                            {{ $row['attended'] }}/{{ $row['total'] }}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height:6px;">
                                                    <div class="progress-bar bg-{{ $row['rate'] >= 75 ? 'success' : ($row['rate'] >= 50 ? 'warning' : 'danger') }}"
                                                         style="width: {{ $row['rate'] }}%"></div>
                                                </div>
                                                <span class="text-tabular fw-semibold" style="font-size:.75rem;min-width:34px;">
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
@endsection
