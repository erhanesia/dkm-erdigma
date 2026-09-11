@extends('layouts.app')

@section('title', 'Pengguna')

@php
    use App\Enums\UserRole;
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Pengguna"
        subtitle="Daftar mentor dan karyawan. Sebagian besar disinkronkan dari sistem HRIS.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Tambah Pengguna
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('users.index')" placeholder="Cari nama, email, NIK, atau jabatan…">
        <div class="col-6 col-md-auto">
            <select name="filter[role]" class="form-select">
                <option value="">Semua peran</option>
                @foreach ($roles as $roleValue => $roleLabel)
                    <option value="{{ $roleValue }}" @selected(request()->input('filter.role') === $roleValue)>
                        {{ $roleLabel }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="filter[department_name]" class="form-select">
                <option value="">Semua departemen</option>
                @foreach ($departments as $department)
                    <option value="{{ $department }}" @selected(request()->input('filter.department_name') === $department)>
                        {{ $department }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select name="filter[is_mentor]" class="form-select">
                <option value="">Semua</option>
                <option value="1" @selected(request()->input('filter.is_mentor') === '1')>Mentor saja</option>
                <option value="0" @selected(request()->input('filter.is_mentor') === '0')>Bukan mentor</option>
            </select>
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($users->isEmpty())
            <x-empty-state icon="people" title="Tidak ada pengguna"
                           text="Belum ada pengguna yang cocok dengan filter ini." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Peran</th>
                            <th class="text-center">Mentor</th>
                            <th class="text-center">Aktif</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $person)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-grid rounded-circle bg-light text-secondary fw-bold flex-shrink-0"
                                              style="width:32px;height:32px;place-items:center;font-size:.6875rem;">
                                            {{ $person->initials() }}
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('users.show', $person) }}"
       wire:navigate
                                               class="fw-semibold text-body d-block text-truncate">
                                                {{ $person->name }}
                                            </a>
                                            <div class="text-body-tertiary text-truncate" style="font-size:.75rem;">
                                                {{ $person->email }}
                                                @if ($person->external_employee_id)
                                                    · {{ $person->external_employee_id }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="font-size:.875rem;">
                                    {{ $person->position_name ?: '—' }}
                                    @if ($person->department_name)
                                        <div class="text-body-tertiary" style="font-size:.75rem;">
                                            {{ $person->department_name }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    @php $roleEnum = $person->primaryRole(); @endphp
                                    <span class="badge {{ $roleEnum?->badgeClass() ?? 'text-bg-light' }}">
                                        {{ $roleEnum?->label() ?? '—' }}
                                    </span>
                                </td>

                                @foreach ([
                                    ['is_mentor', $person->is_mentor],
                                    ['is_active', $person->is_active],
                                ] as [$field, $enabled])
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('users.toggle', $person) }}"
                                              class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="field" value="{{ $field }}">
                                            <input type="hidden" name="value" value="{{ $enabled ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-sm btn-link p-0 border-0">
                                                <i @class([
                                                    'bi fs-5',
                                                    'bi-toggle-on text-success' => $enabled,
                                                    'bi-toggle-off text-body-tertiary' => ! $enabled,
                                                ])></i>
                                            </button>
                                        </form>
                                    </td>
                                @endforeach

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('users.show', $person) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('users.edit', $person) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('users.destroy', $person)"
                                            :title="'Hapus ' . $person->name . '?'"
                                            confirm="Akun ini tidak bisa dipakai masuk lagi."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="p-3 border-top">{{ $users->links() }}</div>
            @endif
        @endif
    </div>
@endsection
