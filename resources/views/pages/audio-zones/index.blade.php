@extends('layouts.app')

@section('title', 'Zona Audio')

@section('content')
    <x-page-header
        title="Zona Audio"
        subtitle="Setiap ruangan yang punya speaker. Adzan dan tilawah diatur terpisah per ruangan.">
        <x-slot:actions>
            <a href="{{ route('audio-zones.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Tambah Zona
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('audio-zones.index')" placeholder="Cari nama, kode, atau lantai…">
        <div class="col-6 col-md-auto">
            <select name="filter[is_active]" class="form-select">
                <option value="">Semua status</option>
                <option value="1" @selected(request()->input('filter.is_active') === '1')>Aktif</option>
                <option value="0" @selected(request()->input('filter.is_active') === '0')>Nonaktif</option>
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="filter[is_murottal_enabled]" class="form-select">
                <option value="">Tilawah: semua</option>
                <option value="1" @selected(request()->input('filter.is_murottal_enabled') === '1')>Tilawah nyala</option>
                <option value="0" @selected(request()->input('filter.is_murottal_enabled') === '0')>Tilawah mati</option>
            </select>
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($zones->isEmpty())
            <x-empty-state
                icon="speaker"
                title="Belum ada zona audio"
                text="Tambahkan ruangan yang memiliki speaker agar adzan dan tilawah bisa dijadwalkan di sana.">
                <x-slot:action>
                    <a href="{{ route('audio-zones.create') }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Zona
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ruangan</th>
                            <th class="text-center">Adzan</th>
                            <th class="text-center">Tilawah</th>
                            <th class="text-center">Volume</th>
                            <th class="text-center">Perangkat</th>
                            <th class="text-center">Jadwal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zones as $zone)
                            <tr>
                                <td>
                                    <a href="{{ route('audio-zones.show', $zone) }}"
       wire:navigate
                                       class="fw-semibold text-body">{{ $zone->name }}</a>
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        <code>{{ $zone->code }}</code>
                                        @if ($zone->floor) · {{ $zone->floor }} @endif
                                        @unless ($zone->is_active)
                                            · <span class="text-danger">nonaktif</span>
                                        @endunless
                                    </div>
                                </td>

                                @foreach ([
                                    ['is_adhan_enabled', $zone->is_adhan_enabled],
                                    ['is_murottal_enabled', $zone->is_murottal_enabled],
                                ] as [$field, $enabled])
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('audio-zones.toggle', $zone) }}"
                                              class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="field" value="{{ $field }}">
                                            <input type="hidden" name="value" value="{{ $enabled ? 0 : 1 }}">
                                            <button type="submit"
                                                    class="btn btn-sm btn-link p-0 border-0"
                                                    title="{{ $enabled ? 'Klik untuk mematikan' : 'Klik untuk menyalakan' }}">
                                                <i @class([
                                                    'bi fs-5',
                                                    'bi-toggle-on text-success' => $enabled,
                                                    'bi-toggle-off text-body-tertiary' => ! $enabled,
                                                ])></i>
                                            </button>
                                        </form>
                                    </td>
                                @endforeach

                                <td class="text-center text-tabular">{{ $zone->default_volume }}%</td>

                                <td class="text-center">
                                    <span class="badge text-bg-light border">{{ $zone->devices_count }}</span>
                                </td>

                                <td class="text-center">
                                    <span class="badge text-bg-light border">{{ $zone->murottal_schedules_count }}</span>
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('audio-zones.show', $zone) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('audio-zones.edit', $zone) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('audio-zones.destroy', $zone)"
                                            :title="'Hapus zona ' . $zone->name . '?'"
                                            confirm="Pengaturan adzan dan jadwal tilawah di zona ini ikut terhapus."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($zones->hasPages())
                <div class="p-3 border-top">{{ $zones->links() }}</div>
            @endif
        @endif
    </div>
@endsection
