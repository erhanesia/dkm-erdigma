@extends('layouts.app')

@section('title', $group->name)

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header :title="$group->name" :subtitle="$group->description ?: 'Kode halaqah: ' . $group->code">
        <x-slot:actions>
            <a href="{{ route('mentoring-groups.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('mentoring-groups.edit', $group) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
            <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card label="Anggota" :value="$members->count()"
                         :meta="'dari kapasitas ' . $group->capacity"
                         icon="people" color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Slot Tersisa" :value="$remainingSlots"
                         icon="person-plus"
                         :color="$remainingSlots > 0 ? 'success' : 'warning'"
                         :meta="$remainingSlots > 0 ? 'Masih bisa menambah' : 'Sudah penuh'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Kegiatan" :value="$sessions->count()"
                         icon="calendar-event" color="info" meta="10 terakhir" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Status" :value="$group->is_active ? 'Aktif' : 'Nonaktif'"
                         :count-up="false" icon="toggle-on"
                         :color="$group->is_active ? 'success' : 'secondary'" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card h-100" data-aos="fade-up">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-0">Mentor</h2>
                </div>

                <div class="d-flex align-items-center gap-3 px-4 py-3 border-top">
                    <span class="d-grid rounded-circle bg-primary text-white fw-bold flex-shrink-0"
                          style="width:44px;height:44px;place-items:center;">
                        {{ $group->mentor?->initials() ?? '?' }}
                    </span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate">{{ $group->mentor?->name ?? 'Belum ada mentor' }}</div>
                        <div class="text-body-tertiary text-truncate" style="font-size:.75rem;">
                            {{ $group->mentor?->jobTitle() ?: $group->mentor?->email }}
                        </div>
                    </div>
                </div>

                <div class="card-body pt-3 pb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="card-title mb-0">Anggota Binaan</h2>
                        <span class="badge text-bg-light border">{{ $members->count() }}</span>
                    </div>
                </div>

                @forelse ($members as $member)
                    <div class="d-flex align-items-center gap-3 px-4 py-2 border-top">
                        <span class="d-grid rounded-circle bg-light text-secondary fw-bold flex-shrink-0"
                              style="width:30px;height:30px;place-items:center;font-size:.6875rem;">
                            {{ $member->initials() }}
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-medium text-truncate" style="font-size:.875rem;">{{ $member->name }}</div>
                            <div class="text-body-tertiary text-truncate" style="font-size:.6875rem;">
                                {{ $member->jobTitle() ?: $member->email }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 pb-3">
                        <p class="text-secondary mb-0" style="font-size:.875rem;">
                            Belum ada anggota. Tambahkan lewat tombol Ubah.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card h-100" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-0">Riwayat Kegiatan</h2>
                            <p class="card-subtitle mb-0">10 kegiatan terakhir</p>
                        </div>
                        <a href="{{ route('sessions.index', ['filter[mentoring_group_id]' => $group->id]) }}"
       wire:navigate
                           class="btn btn-sm btn-light">Semua</a>
                    </div>
                </div>

                @forelse ($sessions as $session)
                    <a href="{{ route('sessions.show', $session) }}"
       wire:navigate
                       class="d-flex align-items-center gap-3 px-4 py-3 border-top text-body text-decoration-none">
                        <div class="text-center flex-shrink-0" style="width:44px;">
                            <div class="fw-bold lh-1">{{ $session->starts_at->format('j') }}</div>
                            <div class="text-secondary text-uppercase" style="font-size:.625rem;">
                                {{ DateHelper::monthName((int) $session->starts_at->format('n')) }}
                            </div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $session->topic }}</div>
                            <div class="text-secondary text-truncate" style="font-size:.75rem;">
                                {{ DateHelper::formatTime($session->starts_at) }}
                                @if ($session->location) · {{ $session->location }} @endif
                            </div>
                        </div>
                        <span class="badge {{ $session->status->badgeClass() }}">
                            {{ $session->status->label() }}
                        </span>
                    </a>
                @empty
                    <x-empty-state
                        icon="calendar-event"
                        title="Belum ada kegiatan"
                        text="Jadwalkan kajian atau mentoring untuk halaqah ini.">
                        <x-slot:action>
                            <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
                            </a>
                        </x-slot:action>
                    </x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
@endsection
