@extends('layouts.app')

@section('title', 'Monitoring Pemutaran')

@php
    use App\Enums\PlaybackStatus;
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Monitoring Pemutaran"
        subtitle="Bukti apakah adzan benar-benar terdengar. Perangkat mengukur level suaranya sendiri lalu melaporkannya ke sini.">
        <x-slot:actions>
            @if ($issues->isNotEmpty())
                <form method="POST" action="{{ route('playback-logs.acknowledge-all') }}"
                      data-confirm="Semua masalah yang belum ditangani akan ditandai selesai."
                      data-confirm-title="Tandai semua sudah ditindaklanjuti?"
                      data-confirm-button="Ya, tandai semua"
                      data-confirm-icon="question">
                    @csrf
                    <button type="submit" class="btn btn-light" data-submitting-label="Menandai…">
                        <i class="bi bi-check2-all me-1"></i> Tandai Semua
                    </button>
                </form>
            @endif
            <a href="{{ route('devices.index') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-pc-display me-1"></i> Perangkat
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Tingkat Keberhasilan"
                :value="$summary['success_rate']"
                :decimals="1"
                suffix="%"
                icon="check2-circle"
                :color="$summary['success_rate'] >= 95 ? 'success' : ($summary['success_rate'] >= 85 ? 'warning' : 'danger')"
                :meta="$summary['played'] . ' dari ' . $summary['total'] . ' terputar'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Tidak Terdengar"
                :value="$summary['silent']"
                icon="volume-mute"
                :color="$summary['silent'] === 0 ? 'success' : 'warning'"
                meta="Audio jalan tapi suara nyaris nol" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Terlewat"
                :value="$summary['missed']"
                icon="exclamation-triangle"
                :color="$summary['missed'] === 0 ? 'success' : 'danger'"
                meta="Tidak ada laporan dari perangkat" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Perangkat Online"
                :value="$deviceHealth['online'] . ' / ' . $deviceHealth['total']"
                :count-up="false"
                icon="pc-display"
                :color="$deviceHealth['offline'] === 0 ? 'success' : 'warning'"
                :href="route('devices.index')" />
        </div>
    </div>

    {{-- Outstanding issues, with a plain-language explanation of what to check. --}}
    @if ($issues->isNotEmpty())
        <div class="card mb-4" data-aos="fade-up">
            <div class="card-body pb-2">
                <h2 class="card-title mb-1 text-danger-emphasis">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Perlu Ditindaklanjuti
                </h2>
                <p class="card-subtitle mb-2">
                    Masalah yang belum ditandai selesai, terbaru di atas.
                </p>
            </div>

            @foreach ($issues as $issue)
                <div class="d-flex flex-wrap align-items-start gap-3 px-4 py-3 border-top">
                    <span class="badge {{ $issue->status->badgeClass() }} mt-1">
                        {{ $issue->status->label() }}
                    </span>

                    <div class="flex-grow-1 min-w-0" style="min-width:220px;">
                        <div class="fw-semibold">
                            {{ $issue->type->label() }}
                            @if ($issue->prayer) {{ $issue->prayer->label() }} @endif
                            <span class="text-secondary fw-normal">· {{ $issue->zone->name }}</span>
                        </div>
                        <div class="text-secondary" style="font-size:.8125rem;">
                            {{ DateHelper::formatDateTime($issue->scheduled_at) }}
                        </div>
                        <div class="text-body-tertiary mt-1" style="font-size:.8125rem;">
                            {{ $issue->diagnosis() }}
                        </div>
                    </div>

                    <form method="POST" action="{{ route('playback-logs.acknowledge', $issue) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-light">
                            <i class="bi bi-check-lg me-1"></i> Sudah ditangani
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card h-100" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-1">Tren Harian</h2>
                    <p class="card-subtitle mb-3">30 hari terakhir</p>

                    @php
                        $trendPayload = [
                            'labels' => array_map(
                                fn ($row) => \Carbon\CarbonImmutable::parse($row['date'])->format('j/n'),
                                $trend,
                            ),
                            'stacked' => true,
                            'datasets' => [
                                ['label' => 'Terputar', 'data' => array_column($trend, 'played'), 'color' => 'success'],
                                ['label' => 'Tidak terdengar', 'data' => array_column($trend, 'silent'), 'color' => 'warning'],
                                ['label' => 'Terlewat / gagal', 'data' => array_map(fn ($r) => $r['missed'] + $r['failed'], $trend), 'color' => 'danger'],
                            ],
                        ];
                    @endphp

                    <div style="height:250px;">
                        <canvas data-chart="bar" data-chart-payload='@json($trendPayload)'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-1">Keandalan per Ruangan</h2>
                    <p class="card-subtitle mb-2">
                        Ruangan yang sering bermasalah biasanya persoalan speaker atau kabel di sana.
                    </p>
                </div>

                @if (empty($byZone))
                    <x-empty-state icon="speaker" title="Belum ada data"
                                   text="Data muncul setelah perangkat mulai melaporkan pemutaran." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ruangan</th>
                                    <th class="text-center">Berhasil</th>
                                    <th class="text-center">Bermasalah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byZone as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['zone'] }}</td>
                                        <td class="text-center text-tabular text-success">{{ $row['played'] }}</td>
                                        <td class="text-center text-tabular {{ $row['problems'] > 0 ? 'text-danger fw-bold' : 'text-body-tertiary' }}">
                                            {{ $row['problems'] }}
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

    <x-filter-bar :action="route('playback-logs.index')" placeholder="—" search-key="filter[__unused]">
        <div class="col-6 col-md-auto">
            <select name="filter[audio_zone_id]" class="form-select">
                <option value="">Semua ruangan</option>
                @foreach ($zones as $zoneId => $zoneName)
                    <option value="{{ $zoneId }}" @selected(request()->input('filter.audio_zone_id') == $zoneId)>
                        {{ $zoneName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="filter[status]" class="form-select">
                <option value="">Semua status</option>
                @foreach ($statuses as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" @selected(request()->input('filter.status') === $statusValue)>
                        {{ $statusLabel }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <input type="date" name="filter[date_from]" value="{{ request()->input('filter.date_from') }}"
                   class="form-control" title="Dari tanggal">
        </div>
        <div class="col-6 col-md-auto">
            <input type="date" name="filter[date_to]" value="{{ request()->input('filter.date_to') }}"
                   class="form-control" title="Sampai tanggal">
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($logs->isEmpty())
            <x-empty-state
                icon="activity"
                title="Belum ada catatan pemutaran"
                text="Catatan muncul setelah jadwal dibuat dan perangkat melaporkan hasilnya." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jadwal</th>
                            <th>Ruangan</th>
                            <th>Jenis</th>
                            <th class="text-center">Level Suara</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-nowrap">
                                    <span class="fw-semibold text-tabular">
                                        {{ DateHelper::formatTime($log->scheduled_at) }}
                                    </span>
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ DateHelper::formatDate($log->scheduled_at) }}
                                    </div>
                                </td>
                                <td>{{ $log->zone->name }}</td>
                                <td>
                                    {{ $log->type->label() }}
                                    @if ($log->prayer)
                                        <span class="text-body-tertiary">· {{ $log->prayer->label() }}</span>
                                    @endif
                                </td>
                                <td class="text-center text-tabular">
                                    @if ($log->peak_audio_level === null)
                                        <span class="text-body-tertiary">—</span>
                                    @else
                                        <span @class([
                                            'fw-semibold' => true,
                                            'text-danger' => $log->status === PlaybackStatus::Silent,
                                        ])>{{ number_format($log->peak_audio_level, 3) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $log->status->badgeClass() }}"
                                          title="{{ $log->diagnosis() }}">
                                        {{ $log->status->label() }}
                                    </span>
                                    @if ($log->is_acknowledged)
                                        <i class="bi bi-check2 text-success ms-1" title="Sudah ditindaklanjuti"></i>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="p-3 border-top">{{ $logs->links() }}</div>
            @endif
        @endif
    </div>
@endsection
