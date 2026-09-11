@extends('layouts.app')

@section('title', 'Perangkat')

@php
    use App\Enums\DeviceStatus;
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Perangkat Player"
        subtitle="Browser yang dibuka di tiap ruangan untuk memutar adzan. Statusnya dipantau dari sini.">
        <x-slot:actions>
            <a href="{{ route('devices.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Daftarkan Perangkat
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <x-stat-card label="Total Perangkat" :value="$health['total']" icon="pc-display" color="secondary" />
        </div>
        <div class="col-6 col-md-4">
            <x-stat-card label="Online" :value="$health['online']" icon="wifi" color="success" />
        </div>
        <div class="col-6 col-md-4">
            <x-stat-card
                label="Offline"
                :value="$health['offline']"
                icon="wifi-off"
                :color="$health['offline'] === 0 ? 'success' : 'danger'"
                :meta="$health['offline'] > 0 ? 'Adzan tidak akan terputar di ruangan ini' : 'Semua terhubung'" />
        </div>
    </div>

    <x-filter-bar :action="route('devices.index')" placeholder="Cari nama perangkat atau ruangan…">
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
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($devices->isEmpty())
            <x-empty-state
                icon="pc-display"
                title="Belum ada perangkat"
                text="Daftarkan PC, tablet, atau mini-PC di tiap ruangan. Setiap perangkat mendapat token untuk membuka halaman player.">
                <x-slot:action>
                    <a href="{{ route('devices.create') }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Daftarkan Perangkat
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Perangkat</th>
                            <th>Ruangan</th>
                            <th>Status</th>
                            <th class="text-center">Volume</th>
                            <th>Terakhir Terlihat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            @php $status = $device->resolveStatus(); @endphp

                            <tr>
                                <td>
                                    <a href="{{ route('devices.show', $device) }}"
       wire:navigate
                                       class="fw-semibold text-body">{{ $device->name }}</a>
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        <code>{{ $device->token_preview }}</code>
                                    </div>
                                </td>

                                <td>{{ $device->zone->name }}</td>

                                <td>
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <span @class([
                                            'status-dot',
                                            'status-dot--live' => $status === DeviceStatus::Online,
                                            'status-dot--muted' => $status === DeviceStatus::Muted,
                                            'status-dot--offline' => in_array($status, [DeviceStatus::Offline, DeviceStatus::NeverConnected], true),
                                            'status-dot--idle' => $status === DeviceStatus::Disabled,
                                        ])></span>
                                        <span style="font-size:.875rem;">{{ $status->label() }}</span>
                                    </span>
                                </td>

                                <td class="text-center text-tabular">{{ $device->volume }}%</td>

                                <td class="text-secondary" style="font-size:.8125rem;">
                                    {{ $device->last_seen_at ? DateHelper::diffForHumans($device->last_seen_at) : 'Belum pernah' }}
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('devices.show', $device) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('devices.edit', $device) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('devices.destroy', $device)"
                                            :title="'Hapus perangkat ' . $device->name . '?'"
                                            confirm="Riwayat denyut dan perintahnya ikut terhapus."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($devices->hasPages())
                <div class="p-3 border-top">{{ $devices->links() }}</div>
            @endif
        @endif
    </div>
@endsection
