@extends('layouts.app')

@section('title', 'Kegiatan After Hours')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Kegiatan After Hours"
        subtitle="Jadwal kajian dan mentoring, beserta daftar hadirnya.">
        <x-slot:actions>
            <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('sessions.index')" placeholder="Cari judul atau materi…">
        <div class="col-6 col-md-auto">
            <select name="filter[mentoring_group_id]" class="form-select">
                <option value="">Semua halaqah</option>
                @foreach ($groups as $groupId => $groupName)
                    <option value="{{ $groupId }}" @selected(request()->input('filter.mentoring_group_id') == $groupId)>
                        {{ $groupName }}
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
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($sessions->isEmpty())
            <x-empty-state
                icon="calendar-event"
                title="Belum ada kegiatan"
                text="Jadwalkan kajian atau mentoring. Daftar hadir akan disiapkan otomatis untuk semua anggota halaqah.">
                <x-slot:action>
                    <a href="{{ route('sessions.create') }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Jadwalkan Kegiatan
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kegiatan</th>
                            <th>Halaqah</th>
                            <th>Waktu</th>
                            <th class="text-center">Kehadiran</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            @php
                                $attending = $session->attending_count ?? 0;
                                $expected = $session->attendances_count ?? 0;
                                $rate = $expected > 0 ? ($attending / $expected) * 100 : 0;
                            @endphp

                            <tr>
                                <td>
                                    <a href="{{ route('sessions.show', $session) }}"
       wire:navigate
                                       class="fw-semibold text-body">{{ $session->topic }}</a>
                                    {{-- The description, not the topic again: the topic
                                         is already the link directly above. --}}
                                    @if ($session->description)
                                        <div class="text-body-tertiary text-truncate" style="font-size:.75rem;max-width:260px;">
                                            {{ $session->description }}
                                        </div>
                                    @endif
                                </td>

                                <td style="font-size:.875rem;">
                                    {{ $session->group->name }}
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ $session->group->mentor?->name ?? '—' }}
                                    </div>
                                </td>

                                <td class="text-nowrap" style="font-size:.875rem;">
                                    {{ DateHelper::formatDate($session->starts_at) }}
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ DateHelper::formatTime($session->starts_at) }}–{{ DateHelper::formatTime($session->ends_at) }}
                                    </div>
                                </td>

                                <td class="text-center" style="min-width:130px;">
                                    @if ($expected === 0)
                                        <span class="text-body-tertiary" style="font-size:.8125rem;">—</span>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:6px;">
                                                <div class="progress-bar bg-{{ $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') }}"
                                                     style="width: {{ $rate }}%"></div>
                                            </div>
                                            <span class="text-tabular" style="font-size:.75rem;min-width:36px;">
                                                {{ $attending }}/{{ $expected }}
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge {{ $session->status->badgeClass() }}">
                                        {{ $session->status->label() }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('attendances.edit', $session) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Isi presensi">
                                            <i class="bi bi-clipboard-check"></i>
                                        </a>
                                        <a href="{{ route('sessions.qr', $session) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Kode QR">
                                            <i class="bi bi-qr-code"></i>
                                        </a>
                                        <a href="{{ route('sessions.edit', $session) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('sessions.destroy', $session)"
                                            :title="'Hapus kegiatan ' . $session->topic . '?'"
                                            confirm="Daftar hadirnya ikut terhapus."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($sessions->hasPages())
                <div class="p-3 border-top">{{ $sessions->links() }}</div>
            @endif
        @endif
    </div>
@endsection
