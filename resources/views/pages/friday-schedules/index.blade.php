@extends('layouts.app')

@section('title', "Jadwal Jum'at")

@php
    use App\Support\Helpers\DateHelper;

    $isAdmin = auth()->user()->isAdministrator();
@endphp

@section('content')
    <x-page-header
        title="Jadwal Jum'at"
        subtitle="Khatib, imam, dan muadzin untuk setiap sholat Jum'at.">
        <x-slot:actions>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
            @if ($isAdmin)
                <a href="{{ route('friday-schedules.create') }}"
       wire:navigate class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($isAdmin && ! empty($unscheduled))
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 mb-4 no-print" data-aos="fade-up">
            <i class="bi bi-calendar-x fs-4"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ count($unscheduled) }} Jum'at belum ada petugas</div>
                <div style="font-size:.875rem;">
                    {{ implode(', ', array_map(fn ($d) => DateHelper::formatDate($d), array_slice($unscheduled, 0, 4))) }}
                    @if (count($unscheduled) > 4) dan {{ count($unscheduled) - 4 }} lainnya @endif
                </div>
            </div>
            <a href="{{ route('friday-schedules.create', ['date' => $unscheduled[0]]) }}"
       wire:navigate
               class="btn btn-sm btn-warning fw-semibold">
                Atur yang terdekat
            </a>
        </div>
    @endif

    {{-- Upcoming, rendered as cards because these are the ones people check. --}}
    @if ($upcoming->isNotEmpty())
        <div class="row g-3 mb-4">
            @foreach ($upcoming as $schedule)
                <div class="col-12 col-sm-6 col-xl-3" data-aos="fade-up" data-aos-delay="{{ $loop->index * 60 }}">
                    <div class="card h-100 card-hover">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <div class="fw-bold">{{ DateHelper::formatDate($schedule->date) }}</div>
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ $schedule->date->isToday() ? 'Hari ini' : DateHelper::diffForHumans($schedule->date) }}
                                    </div>
                                </div>
                                <span class="badge {{ $schedule->status->badgeClass() }}">
                                    {{ $schedule->status->label() }}
                                </span>
                            </div>

                            @foreach ([
                                ['Khatib', $schedule->khatibName(), 'mic'],
                                ['Imam', $schedule->imam?->name ?? 'Belum ada', 'person'],
                                ['Muadzin', $schedule->muadzin?->name ?? 'Belum ada', 'megaphone'],
                            ] as [$roleLabel, $personName, $roleIcon])
                                <div class="d-flex align-items-center gap-2 py-1" style="font-size:.8125rem;">
                                    <i class="bi bi-{{ $roleIcon }} text-secondary"></i>
                                    <span class="text-secondary" style="min-width:56px;">{{ $roleLabel }}</span>
                                    <span class="fw-medium text-truncate">{{ $personName }}</span>
                                </div>
                            @endforeach

                            @if ($schedule->theme)
                                <div class="mt-2 pt-2 border-top text-secondary" style="font-size:.75rem;">
                                    {{ $schedule->theme }}
                                </div>
                            @endif

                            @if ($schedule->missingRoles())
                                <div class="mt-2 text-warning-emphasis" style="font-size:.75rem;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Belum: {{ implode(', ', $schedule->missingRoles()) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-filter-bar :action="route('friday-schedules.index')" placeholder="Cari tema atau nama khatib…">
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
            <input type="month" name="filter[month]" value="{{ request()->input('filter.month') }}"
                   class="form-control">
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($schedules->isEmpty())
            <x-empty-state
                icon="calendar-week"
                title="Belum ada jadwal Jum'at"
                :text="$isAdmin ? 'Tambahkan jadwal agar petugas tahu giliran mereka jauh hari.' : 'Pengurus DKM belum mengatur jadwal.'">
                @if ($isAdmin)
                    <x-slot:action>
                        <a href="{{ route('friday-schedules.create') }}"
       wire:navigate class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
                        </a>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Khatib</th>
                            <th>Imam</th>
                            <th>Muadzin</th>
                            <th>Tema</th>
                            <th>Status</th>
                            @if ($isAdmin)
                                <th class="text-end no-print">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td class="text-nowrap">
                                    <span class="fw-semibold">{{ DateHelper::formatDate($schedule->date) }}</span>
                                    @if ($schedule->start_time)
                                        <div class="text-body-tertiary" style="font-size:.75rem;">
                                            {{ substr($schedule->start_time, 0, 5) }}
                                            @if ($schedule->location) · {{ $schedule->location }} @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    {{ $schedule->khatibName() }}
                                    @if ($schedule->external_khatib_origin)
                                        <div class="text-body-tertiary" style="font-size:.75rem;">
                                            {{ $schedule->external_khatib_origin }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $schedule->imam?->name ?? '—' }}</td>
                                <td>{{ $schedule->muadzin?->name ?? '—' }}</td>
                                <td class="text-secondary" style="font-size:.875rem;max-width:220px;">
                                    <span class="d-inline-block text-truncate w-100">{{ $schedule->theme ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $schedule->status->badgeClass() }}">
                                        {{ $schedule->status->label() }}
                                    </span>
                                </td>
                                @if ($isAdmin)
                                    <td class="text-end no-print">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('friday-schedules.show', $schedule) }}"
       wire:navigate
                                               class="btn btn-sm btn-light" title="Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('friday-schedules.edit', $schedule) }}"
       wire:navigate
                                               class="btn btn-sm btn-light" title="Ubah">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <x-delete-button
                                                :action="route('friday-schedules.destroy', $schedule)"
                                                :title="'Hapus jadwal ' . DateHelper::formatDate($schedule->date) . '?'"
                                                confirm="Data petugas untuk tanggal ini akan hilang."
                                                icon-only />
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($schedules->hasPages())
                <div class="p-3 border-top no-print">{{ $schedules->links() }}</div>
            @endif
        @endif
    </div>
@endsection
