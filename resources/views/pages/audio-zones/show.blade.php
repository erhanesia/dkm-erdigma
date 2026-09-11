@extends('layouts.app')

@section('title', $zone->name)

@php
    use App\Enums\DeviceCommandType;
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header :title="$zone->name" :subtitle="$zone->description ?: 'Pengaturan audio untuk ruangan ini.'">
        <x-slot:actions>
            <a href="{{ route('audio-zones.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('audio-zones.edit', $zone) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            {{-- Quick switches: the answer to "matikan tilawah di ruangan CEO". --}}
            <div class="card" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-3">Saklar Cepat</h2>

                    <div class="row g-2">
                        @foreach ([
                            ['is_active', 'Zona Aktif', $zone->is_active, 'power'],
                            ['is_adhan_enabled', 'Adzan', $zone->is_adhan_enabled, 'megaphone'],
                            ['is_murottal_enabled', 'Tilawah', $zone->is_murottal_enabled, 'book'],
                        ] as [$field, $switchLabel, $enabled, $switchIcon])
                            <div class="col-12 col-sm-4">
                                <form method="POST" action="{{ route('audio-zones.toggle', $zone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="field" value="{{ $field }}">
                                    <input type="hidden" name="value" value="{{ $enabled ? 0 : 1 }}">
                                    <button type="submit"
                                            @class([
                                                'btn w-100 d-flex align-items-center justify-content-between px-3 py-2',
                                                'btn-success' => $enabled,
                                                'btn-light border' => ! $enabled,
                                            ])>
                                        <span class="d-flex align-items-center gap-2">
                                            <i class="bi bi-{{ $switchIcon }}"></i>
                                            <span class="fw-semibold">{{ $switchLabel }}</span>
                                        </span>
                                        <i class="bi bi-{{ $enabled ? 'toggle-on' : 'toggle-off' }} fs-5"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <p class="form-hint mb-0 mt-2">
                        Perubahan langsung dikirim ke perangkat di ruangan ini — tidak perlu datang ke sana.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            {{-- Remote control: test the speakers without leaving the office. --}}
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-1">Kirim Perintah</h2>
                    <p class="card-subtitle mb-3">Dijalankan perangkat pada pengecekan berikutnya.</p>

                    <form method="POST" action="{{ route('audio-zones.commands.store', $zone) }}"
                          class="d-flex gap-2">
                        @csrf
                        <select name="type" class="form-select">
                            @foreach ([
                                DeviceCommandType::SelfTest,
                                DeviceCommandType::Stop,
                                DeviceCommandType::ReloadPlan,
                                DeviceCommandType::Refresh,
                            ] as $commandType)
                                <option value="{{ $commandType->value }}">{{ $commandType->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary flex-shrink-0">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>

                    <p class="form-hint mb-0 mt-2">
                        <strong>Uji Speaker</strong> memutar nada pendek lalu melaporkan level suara
                        yang terukur — cara cepat memastikan speaker benar-benar hidup.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-prayer adhan configuration --}}
    <div class="card mb-4" data-aos="fade-up">
        <div class="card-body">
            <h2 class="card-title mb-1">Pengaturan Adzan per Waktu Sholat</h2>
            <p class="card-subtitle mb-3">
                Setiap waktu sholat bisa punya pengaturan berbeda di ruangan ini.
            </p>

            <form method="POST" action="{{ route('audio-zones.prayer-settings.update', $zone) }}">
                @csrf
                @method('PUT')

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width:110px;">Waktu</th>
                                <th class="text-center">Adzan</th>
                                <th style="min-width:170px;">Audio Adzan</th>
                                <th class="text-center" style="min-width:90px;">Volume</th>
                                <th class="text-center" style="min-width:90px;">Koreksi</th>
                                <th class="text-center">Tarhim</th>
                                <th class="text-center">Iqamah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prayers as $prayer)
                                @php $setting = $settings->get($prayer->value); @endphp

                                <tr>
                                    <td>
                                        <span class="d-flex align-items-center gap-2 fw-semibold">
                                            <i class="bi bi-{{ $prayer->icon() }} text-{{ $prayer->color() }}"></i>
                                            {{ $prayer->label() }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block mb-0">
                                            <input type="hidden"
                                                   name="settings[{{ $prayer->value }}][is_adhan_enabled]" value="0">
                                            <input type="checkbox"
                                                   name="settings[{{ $prayer->value }}][is_adhan_enabled]"
                                                   value="1"
                                                   class="form-check-input"
                                                   @checked($setting?->is_adhan_enabled ?? true)>
                                        </div>
                                    </td>

                                    <td>
                                        <select name="settings[{{ $prayer->value }}][adhan_track_id]"
                                                class="form-select form-select-sm">
                                            <option value="">Pakai default</option>
                                            @foreach ($adhanTracks as $trackId => $trackTitle)
                                                <option value="{{ $trackId }}"
                                                        @selected($setting?->adhan_track_id === $trackId)>
                                                    {{ $trackTitle }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="settings[{{ $prayer->value }}][volume]"
                                               value="{{ $setting?->volume ?? $zone->default_volume }}"
                                               min="0" max="100"
                                               class="form-control form-control-sm text-center">
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="settings[{{ $prayer->value }}][offset_minutes]"
                                               value="{{ $setting?->offset_minutes ?? 0 }}"
                                               min="-30" max="30"
                                               class="form-control form-control-sm text-center"
                                               title="Menit lebih awal (-) atau lebih lambat (+)">
                                    </td>

                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block mb-0">
                                            <input type="hidden"
                                                   name="settings[{{ $prayer->value }}][is_tarhim_enabled]" value="0">
                                            <input type="checkbox"
                                                   name="settings[{{ $prayer->value }}][is_tarhim_enabled]"
                                                   value="1"
                                                   class="form-check-input"
                                                   @checked($setting?->is_tarhim_enabled ?? false)>
                                        </div>
                                        <input type="hidden"
                                               name="settings[{{ $prayer->value }}][tarhim_lead_minutes]"
                                               value="{{ $setting?->tarhim_lead_minutes ?? 10 }}">
                                        <input type="hidden"
                                               name="settings[{{ $prayer->value }}][tarhim_track_id]"
                                               value="{{ $setting?->tarhim_track_id }}">
                                    </td>

                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block mb-0">
                                            <input type="hidden"
                                                   name="settings[{{ $prayer->value }}][is_iqamah_enabled]" value="0">
                                            <input type="checkbox"
                                                   name="settings[{{ $prayer->value }}][is_iqamah_enabled]"
                                                   value="1"
                                                   class="form-check-input"
                                                   @checked($setting?->is_iqamah_enabled ?? false)>
                                        </div>
                                        <input type="hidden"
                                               name="settings[{{ $prayer->value }}][iqamah_delay_minutes]"
                                               value="{{ $setting?->iqamah_delay_minutes ?? 10 }}">
                                        <input type="hidden"
                                               name="settings[{{ $prayer->value }}][iqamah_track_id]"
                                               value="{{ $setting?->iqamah_track_id }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('settings')
                    <div class="alert alert-danger py-2 px-3">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn btn-primary mt-2">
                    <i class="bi bi-check-lg me-1"></i> Simpan Pengaturan Adzan
                </button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        {{-- Devices in this room --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100" data-aos="fade-up">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <h2 class="card-title mb-0">Perangkat</h2>
                        <a href="{{ route('devices.create') }}"
       wire:navigate class="btn btn-sm btn-light">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>

                @forelse ($zone->devices as $device)
                    @php $status = $device->resolveStatus(); @endphp
                    <a href="{{ route('devices.show', $device) }}"
       wire:navigate
                       class="d-flex align-items-center gap-3 px-4 py-3 border-top text-body text-decoration-none">
                        <span @class([
                            'status-dot',
                            'status-dot--live' => $status->isHealthy(),
                            'status-dot--muted' => $status === \App\Enums\DeviceStatus::Muted,
                            'status-dot--offline' => in_array($status, [\App\Enums\DeviceStatus::Offline, \App\Enums\DeviceStatus::NeverConnected], true),
                            'status-dot--idle' => $status === \App\Enums\DeviceStatus::Disabled,
                        ])></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $device->name }}</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                {{ $status->label() }}
                                @if ($device->last_seen_at)
                                    · {{ DateHelper::diffForHumans($device->last_seen_at) }}
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $status->badgeClass() }}">{{ $device->volume }}%</span>
                    </a>
                @empty
                    <x-empty-state
                        icon="pc-display"
                        title="Belum ada perangkat"
                        text="Daftarkan perangkat agar ruangan ini bisa memutar audio." />
                @endforelse
            </div>
        </div>

        {{-- Murottal schedules --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <h2 class="card-title mb-0">Jadwal Tilawah</h2>
                        <a href="{{ route('murottal-schedules.create') }}"
       wire:navigate class="btn btn-sm btn-light">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>

                @forelse ($zone->murottalSchedules as $murottal)
                    <div class="d-flex align-items-center gap-3 px-4 py-3 border-top">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $murottal->name }}</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                {{ $murottal->humanWindow() }} · {{ $murottal->humanDays() }}
                            </div>
                        </div>
                        <span class="badge {{ $murottal->is_active ? 'text-bg-success' : 'text-bg-light' }}">
                            {{ $murottal->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                @empty
                    <x-empty-state
                        icon="music-note-list"
                        :title="$zone->is_murottal_enabled ? 'Belum ada jadwal tilawah' : 'Tilawah dimatikan'"
                        :text="$zone->is_murottal_enabled
                            ? 'Tambahkan jadwal agar murottal diputar di ruangan ini.'
                            : 'Tilawah sengaja dimatikan untuk ruangan ini. Nyalakan lewat saklar di atas jika ingin diaktifkan.'" />
                @endforelse
            </div>
        </div>
    </div>
@endsection
