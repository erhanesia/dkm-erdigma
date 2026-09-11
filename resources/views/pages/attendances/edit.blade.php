@extends('layouts.app')

@section('title', 'Isi Presensi')

@php
    use App\Enums\AttendanceStatus;
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Isi Presensi"
        :subtitle="$session->topic . ' — ' . $session->humanSchedule()">
        <x-slot:actions>
            <a href="{{ route('sessions.show', $session) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('sessions.qr', $session) }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-qr-code me-1"></i> Kode QR
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($attendances->isEmpty())
        <div class="table-card">
            <x-empty-state
                icon="clipboard-x"
                title="Halaqah ini belum punya anggota"
                text="Tambahkan anggota dulu di halaman halaqah, lalu daftar hadirnya akan muncul di sini.">
                <x-slot:action>
                    <a href="{{ route('mentoring-groups.edit', $session->group) }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-people me-1"></i> Kelola Anggota
                    </a>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <form method="POST" action="{{ route('attendances.update', $session) }}">
            @csrf
            @method('PUT')

            {{-- Bulk shortcuts: with ten people, clicking each row is tedious when
                 almost everyone showed up. --}}
            <div class="card mb-3 no-print" data-aos="fade-up">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-secondary" style="font-size:.875rem;">Tandai semua:</span>
                        @foreach ([AttendanceStatus::Present, AttendanceStatus::Absent] as $bulkStatus)
                            <button type="button"
                                    class="btn btn-sm btn-light"
                                    onclick="document.querySelectorAll('[data-status-select]').forEach(s => s.value = '{{ $bulkStatus->value }}')">
                                <i class="bi bi-{{ $bulkStatus->icon() }} me-1 text-{{ $bulkStatus->color() }}"></i>
                                {{ $bulkStatus->label() }}
                            </button>
                        @endforeach

                        <div class="ms-auto d-flex flex-wrap gap-2">
                            @foreach (AttendanceStatus::cases() as $status)
                                <span class="badge {{ $status->badgeClass() }}">
                                    {{ $status->label() }}: {{ $tally[$status->value] ?? 0 }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card mb-3" data-aos="fade-up" data-aos-delay="60">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px;">#</th>
                                <th>Nama</th>
                                <th style="min-width:180px;">Status</th>
                                <th style="min-width:220px;">Catatan</th>
                                <th class="text-center">Check-in</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendances as $attendance)
                                <tr>
                                    <td class="text-secondary text-tabular">{{ $loop->iteration }}</td>

                                    <td>
                                        <input type="hidden"
                                               name="entries[{{ $loop->index }}][user_id]"
                                               value="{{ $attendance->user_id }}">

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-grid rounded-circle bg-light text-secondary fw-bold flex-shrink-0"
                                                  style="width:30px;height:30px;place-items:center;font-size:.6875rem;">
                                                {{ $attendance->user->initials() }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="fw-medium text-truncate">{{ $attendance->user->name }}</div>
                                                <div class="text-body-tertiary text-truncate" style="font-size:.6875rem;">
                                                    {{ $attendance->user->jobTitle() ?: $attendance->user->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <select name="entries[{{ $loop->index }}][status]"
                                                class="form-select form-select-sm"
                                                data-status-select>
                                            @foreach (AttendanceStatus::cases() as $status)
                                                <option value="{{ $status->value }}"
                                                        @selected($attendance->status === $status)>
                                                    {{ $status->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input type="text"
                                               name="entries[{{ $loop->index }}][note]"
                                               value="{{ $attendance->note }}"
                                               class="form-control form-control-sm"
                                               placeholder="Opsional">
                                    </td>

                                    <td class="text-center text-tabular text-secondary" style="font-size:.8125rem;">
                                        @if ($attendance->checked_in_at)
                                            {{ DateHelper::formatTime($attendance->checked_in_at) }}
                                            <div class="text-body-tertiary" style="font-size:.625rem;">
                                                {{ $attendance->method->label() }}
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Simpan Presensi
                </button>
                <a href="{{ route('sessions.show', $session) }}"
       wire:navigate class="btn btn-light">Batal</a>
            </div>
        </form>
    @endif
@endsection
