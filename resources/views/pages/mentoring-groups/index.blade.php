@extends('layouts.app')

@section('title', 'Halaqah')

@section('content')
    <x-page-header
        title="Halaqah"
        subtitle="Kelompok mentoring. Satu mentor (ustadz) membina sekitar 10 karyawan.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('mentoring-groups.create') }}"
       wire:navigate class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Halaqah
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('mentoring-groups.index')" placeholder="Cari nama halaqah atau mentor…">
        @if ($canManage)
            <div class="col-6 col-md-auto">
                <select name="filter[mentor_id]" class="form-select">
                    <option value="">Semua mentor</option>
                    @foreach ($mentors as $mentorId => $mentorName)
                        <option value="{{ $mentorId }}" @selected(request()->input('filter.mentor_id') == $mentorId)>
                            {{ $mentorName }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-6 col-md-auto">
            <select name="filter[is_active]" class="form-select">
                <option value="">Semua status</option>
                <option value="1" @selected(request()->input('filter.is_active') === '1')>Aktif</option>
                <option value="0" @selected(request()->input('filter.is_active') === '0')>Nonaktif</option>
            </select>
        </div>
    </x-filter-bar>

    @if ($groups->isEmpty())
        <div class="table-card" data-aos="fade-up">
            <x-empty-state
                icon="people"
                title="Belum ada halaqah"
                :text="$canManage
                    ? 'Buat halaqah, tentukan mentornya, lalu masukkan karyawan yang dibina.'
                    : 'Anda belum ditugaskan membina halaqah mana pun.'">
                @if ($canManage)
                    <x-slot:action>
                        <a href="{{ route('mentoring-groups.create') }}"
       wire:navigate class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Halaqah
                        </a>
                    </x-slot:action>
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="row g-3">
            @foreach ($groups as $group)
                @php
                    $memberCount = $group->active_members_count ?? 0;
                    $fillRatio = $group->capacity > 0 ? ($memberCount / $group->capacity) * 100 : 0;
                @endphp

                <div class="col-12 col-md-6 col-xl-4"
                     data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 60 }}">
                    <div class="card h-100 card-hover">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="min-w-0">
                                    <a href="{{ route('mentoring-groups.show', $group) }}"
       wire:navigate
                                       class="card-title text-body d-block text-truncate">
                                        {{ $group->name }}
                                    </a>
                                    <div class="card-subtitle">{{ $group->code }}</div>
                                </div>
                                <span class="badge {{ $group->is_active ? 'text-bg-success' : 'text-bg-light border' }}">
                                    {{ $group->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="d-grid rounded-circle bg-light text-secondary fw-bold flex-shrink-0"
                                      style="width:30px;height:30px;place-items:center;font-size:.6875rem;">
                                    {{ $group->mentor?->initials() ?? '?' }}
                                </span>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate" style="font-size:.875rem;">
                                        {{ $group->mentor?->name ?? 'Tanpa mentor' }}
                                    </div>
                                    <div class="text-body-tertiary" style="font-size:.6875rem;">Mentor</div>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-secondary" style="font-size:.8125rem;">Anggota</span>
                                    <span class="fw-semibold text-tabular" style="font-size:.8125rem;">
                                        {{ $memberCount }} / {{ $group->capacity }}
                                    </span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-{{ $fillRatio >= 100 ? 'warning' : 'primary' }}"
                                         style="width: {{ min(100, $fillRatio) }}%"></div>
                                </div>
                            </div>

                            <div class="d-flex gap-1 mt-3 pt-3 border-top">
                                <a href="{{ route('mentoring-groups.show', $group) }}"
       wire:navigate
                                   class="btn btn-sm btn-light flex-grow-1">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </a>
                                <a href="{{ route('mentoring-groups.edit', $group) }}"
       wire:navigate
                                   class="btn btn-sm btn-light" title="Ubah">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if ($canManage)
                                    <x-delete-button
                                        :action="route('mentoring-groups.destroy', $group)"
                                        :title="'Hapus halaqah ' . $group->name . '?'"
                                        confirm="Hanya bisa dihapus kalau belum punya riwayat kegiatan."
                                        icon-only />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($groups->hasPages())
            <div class="mt-3">{{ $groups->links() }}</div>
        @endif
    @endif
@endsection
