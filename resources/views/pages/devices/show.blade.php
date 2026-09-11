@extends('layouts.app')

@section('title', $device->name)

@php
    use App\Enums\DeviceCommandType;
    use App\Enums\DeviceStatus;
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;
@endphp

@section('content')
    <x-page-header :title="$device->name" :subtitle="'Perangkat di ' . $device->zone->name . '.'">
        <x-slot:actions>
            <a href="{{ route('devices.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('devices.edit', $device) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Shown once, right after registration or a token rotation. --}}
    @if ($plainToken)
        <div class="card border-0 mb-4" style="background:#ecfdf7;" data-aos="fade-up">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-key-fill fs-4 text-primary"></i>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="card-title mb-1">Token Perangkat</h2>
                        <p class="card-subtitle mb-2">
                            Salin sekarang — token ini <strong>tidak akan ditampilkan lagi</strong>.
                            Yang tersimpan hanya hash-nya.
                        </p>

                        <div class="d-flex gap-2" x-data="{ copied: false }">
                            <input type="text" readonly value="{{ $plainToken }}"
                                   class="form-control font-monospace" style="font-size:.8125rem;"
                                   x-ref="token"
                                   onclick="this.select()">
                            <button type="button" class="btn btn-primary flex-shrink-0"
                                    @click="navigator.clipboard.writeText($refs.token.value); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="! copied"><i class="bi bi-clipboard me-1"></i> Salin</span>
                                <span x-show="copied" x-cloak><i class="bi bi-check-lg me-1"></i> Tersalin</span>
                            </button>
                        </div>

                        <p class="form-hint mb-0 mt-2">
                            Buka <a href="{{ route('player.show') }}" target="_blank">{{ route('player.show') }}</a>
                            di perangkat ruangan, lalu tempelkan token ini.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Status"
                :value="$status->label()"
                :count-up="false"
                :icon="$status->icon()"
                :color="$status->color()"
                :meta="$status->description()" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Volume"
                :value="$device->volume"
                suffix="%"
                icon="volume-up"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Level Audio Terakhir"
                :value="$device->last_audio_level !== null ? number_format($device->last_audio_level, 3) : '—'"
                :count-up="false"
                icon="soundwave"
                :color="($device->last_audio_level ?? 0) >= (float) config('dkm.playback.min_audio_level') ? 'success' : 'warning'"
                meta="Diukur perangkat dari output audionya sendiri" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                label="Terakhir Terlihat"
                :value="$device->last_seen_at ? DateHelper::diffForHumans($device->last_seen_at) : 'Belum pernah'"
                :count-up="false"
                icon="clock-history"
                color="secondary"
                :meta="$device->app_version ? 'Versi ' . $device->app_version : null" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-1">Kirim Perintah</h2>
                    <p class="card-subtitle mb-3">Dijalankan saat perangkat mengecek berikutnya.</p>

                    <form method="POST" action="{{ route('devices.commands.store', $device) }}"
                          class="d-flex gap-2 mb-3">
                        @csrf
                        <select name="type" class="form-select">
                            @foreach ($commandTypes as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary flex-shrink-0">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('devices.token.rotate', $device) }}"
                          data-confirm="Token lama langsung tidak berlaku. Anda harus memasukkan token baru di perangkat ruangan."
                          data-confirm-title="Buat token baru?"
                          data-confirm-button="Ya, buat baru">
                        @csrf
                        <button type="submit" class="btn btn-light w-100 text-warning-emphasis" data-submitting-label="Membuat token…">
                            <i class="bi bi-arrow-repeat me-1"></i> Buat Token Baru
                        </button>
                    </form>
                </div>
            </div>

            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-0">Riwayat Perintah</h2>
                </div>

                @forelse ($commands as $command)
                    <div class="d-flex align-items-center gap-3 px-4 py-2 border-top">
                        <i class="bi bi-{{ $command->type->icon() }} text-{{ $command->type->color() }}"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold" style="font-size:.875rem;">{{ $command->type->label() }}</div>
                            <div class="text-body-tertiary" style="font-size:.75rem;">
                                {{ DateHelper::diffForHumans($command->created_at) }}
                                @if ($command->issuer) · {{ $command->issuer->name }} @endif
                            </div>
                        </div>
                        <span class="badge {{ $command->status->badgeClass() }}">
                            {{ $command->status->label() }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 pb-3 text-secondary" style="font-size:.875rem;">
                        Belum ada perintah yang dikirim.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="120">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <h2 class="card-title mb-0">Riwayat Pemutaran</h2>
                            <p class="card-subtitle mb-0">15 pemutaran terakhir dari perangkat ini</p>
                        </div>
                        <a href="{{ route('playback-logs.index') }}"
       wire:navigate class="btn btn-sm btn-light">Semua</a>
                    </div>
                </div>

                @if ($recentPlaybacks->isEmpty())
                    <x-empty-state
                        icon="soundwave"
                        title="Belum ada riwayat"
                        text="Riwayat muncul setelah perangkat ini memutar audio dan melaporkannya." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Jenis</th>
                                    <th class="text-center">Level</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPlaybacks as $log)
                                    <tr>
                                        <td class="text-nowrap" style="font-size:.875rem;">
                                            {{ DateHelper::formatTime($log->scheduled_at) }}
                                            <div class="text-body-tertiary" style="font-size:.6875rem;">
                                                {{ DateHelper::formatDate($log->scheduled_at) }}
                                            </div>
                                        </td>
                                        <td style="font-size:.875rem;">
                                            {{ $log->type->label() }}
                                            @if ($log->prayer)
                                                <span class="text-body-tertiary">· {{ $log->prayer->label() }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-tabular" style="font-size:.8125rem;">
                                            {{ $log->peak_audio_level !== null ? number_format($log->peak_audio_level, 3) : '—' }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge {{ $log->status->badgeClass() }}"
                                                  title="{{ $log->diagnosis() }}">
                                                {{ $log->status->label() }}
                                            </span>
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
