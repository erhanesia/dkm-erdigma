@extends('layouts.app')

@section('title', 'Log Aktivitas')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Log Aktivitas"
        subtitle="Catatan siapa mengubah apa. Berguna saat ada pengaturan yang tiba-tiba berbeda.">
        <x-slot:actions>
            <a href="{{ route('settings.edit') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Pengaturan
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4">
            <label class="form-label">Jenis Data</label>
            <select name="log_name" class="form-select" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($logNames as $logName)
                    <option value="{{ $logName }}" @selected(request('log_name') === $logName)>
                        {{ $logName }}
                    </option>
                @endforeach
            </select>
        </div>
        @if (request('log_name'))
            <div class="col-auto">
                <a href="{{ route('settings.activity-log') }}"
       wire:navigate class="btn btn-light">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        @endif
    </form>

    <div class="table-card" data-aos="fade-up">
        @if ($activities->isEmpty())
            <x-empty-state
                icon="journal-text"
                title="Belum ada aktivitas tercatat"
                text="Catatan muncul setiap ada data yang dibuat, diubah, atau dihapus." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pelaku</th>
                            <th>Jenis Data</th>
                            <th>Aksi</th>
                            <th>Perubahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td class="text-nowrap" style="font-size:.875rem;">
                                    {{ DateHelper::formatTime($activity->created_at) }}
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ DateHelper::formatDate($activity->created_at) }}
                                    </div>
                                </td>

                                <td style="font-size:.875rem;">
                                    {{ $activity->causer?->name ?? 'Sistem' }}
                                </td>

                                <td>
                                    <span class="badge text-bg-light border">{{ $activity->log_name }}</span>
                                </td>

                                <td style="font-size:.875rem;">{{ $activity->description }}</td>

                                <td>
                                    @php
                                        // activitylog v5 dropped `changes()`; the
                                        // before/after values now live in the
                                        // `properties` payload.
                                        $attributes = (array) $activity->getProperty('attributes', []);
                                        $old = (array) $activity->getProperty('old', []);
                                    @endphp

                                    @if (empty($attributes))
                                        <span class="text-body-tertiary" style="font-size:.8125rem;">—</span>
                                    @else
                                        <div class="d-flex flex-column gap-1">
                                            @foreach (array_slice($attributes, 0, 4, true) as $field => $newValue)
                                                <div style="font-size:.75rem;">
                                                    <span class="text-secondary">{{ $field }}:</span>
                                                    @if (array_key_exists($field, $old))
                                                        <span class="text-danger text-decoration-line-through">
                                                            {{ \Illuminate\Support\Str::limit((string) ($old[$field] ?? '—'), 24) }}
                                                        </span>
                                                        <i class="bi bi-arrow-right text-body-tertiary"></i>
                                                    @endif
                                                    <span class="text-success fw-semibold">
                                                        {{ \Illuminate\Support\Str::limit((string) ($newValue ?? '—'), 24) }}
                                                    </span>
                                                </div>
                                            @endforeach

                                            @if (count($attributes) > 4)
                                                <div class="text-body-tertiary" style="font-size:.6875rem;">
                                                    +{{ count($attributes) - 4 }} kolom lain
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($activities->hasPages())
                <div class="p-3 border-top">{{ $activities->links() }}</div>
            @endif
        @endif
    </div>
@endsection
