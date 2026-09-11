@extends('layouts.app')

@section('title', 'Jadwal Tilawah')

@section('content')
    <x-page-header
        title="Jadwal Tilawah"
        subtitle="Jendela waktu murottal per ruangan. Otomatis berhenti sebelum adzan agar tidak bertabrakan.">
        <x-slot:actions>
            <a href="{{ route('murottal-schedules.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('murottal-schedules.index')" placeholder="Cari nama jadwal…">
        <div class="col-6 col-md-auto">
            <select name="filter[audio_zone_id]" class="form-select">
                <option value="">Semua zona</option>
                @foreach ($zones as $zoneId => $zoneName)
                    <option value="{{ $zoneId }}" @selected(request()->input('filter.audio_zone_id') == $zoneId)>
                        {{ $zoneName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="filter[is_active]" class="form-select">
                <option value="">Semua status</option>
                <option value="1" @selected(request()->input('filter.is_active') === '1')>Aktif</option>
                <option value="0" @selected(request()->input('filter.is_active') === '0')>Nonaktif</option>
            </select>
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($schedules->isEmpty())
            <x-empty-state
                icon="music-note-list"
                title="Belum ada jadwal tilawah"
                text="Tentukan kapan murottal diputar di tiap ruangan. Ruangan yang tidak ingin ada tilawah cukup dimatikan saklarnya di halaman zona.">
                <x-slot:action>
                    <a href="{{ route('murottal-schedules.create') }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Ruangan</th>
                            <th>Waktu</th>
                            <th>Hari</th>
                            <th>Audio</th>
                            <th class="text-center">Volume</th>
                            <th class="text-center">Aktif</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $schedule->name }}</span>
                                    @if ($schedule->stops_before_adhan)
                                        <div class="text-body-tertiary" style="font-size:.75rem;">
                                            <i class="bi bi-pause-circle"></i> berhenti sebelum adzan
                                        </div>
                                    @endif
                                </td>

                                <td>{{ $schedule->zone->name }}</td>

                                <td class="text-tabular text-nowrap">{{ $schedule->humanWindow() }}</td>

                                <td class="text-secondary" style="font-size:.8125rem;">
                                    {{ $schedule->humanDays() }}
                                </td>

                                <td class="text-truncate" style="max-width:180px;font-size:.875rem;">
                                    {{ $schedule->track?->title ?? 'Pakai default' }}
                                </td>

                                <td class="text-center text-tabular">{{ $schedule->volume }}%</td>

                                <td class="text-center">
                                    <form method="POST" action="{{ route('murottal-schedules.toggle', $schedule) }}"
                                          class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="value" value="{{ $schedule->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm btn-link p-0 border-0">
                                            <i @class([
                                                'bi fs-5',
                                                'bi-toggle-on text-success' => $schedule->is_active,
                                                'bi-toggle-off text-body-tertiary' => ! $schedule->is_active,
                                            ])></i>
                                        </button>
                                    </form>
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('murottal-schedules.edit', $schedule) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('murottal-schedules.destroy', $schedule)"
                                            :title="'Hapus jadwal ' . $schedule->name . '?'"
                                            confirm="Murottal tidak akan diputar lagi pada jam tersebut."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($schedules->hasPages())
                <div class="p-3 border-top">{{ $schedules->links() }}</div>
            @endif
        @endif
    </div>
@endsection
