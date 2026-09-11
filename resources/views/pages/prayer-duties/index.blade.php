@extends('layouts.app')

@section('title', 'Petugas Sholat')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Petugas Sholat"
        subtitle="Giliran muadzin dan imam harian. Diisi satu minggu sekaligus.">
        <x-slot:actions>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Week navigation --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('prayer-duties.index', ['week' => $weekStart->subWeek()->toDateString()]) }}"
       wire:navigate
               class="btn btn-light"><i class="bi bi-chevron-left"></i></a>

            <div class="text-center px-2">
                <div class="fw-bold">
                    {{ $weekStart->day }} {{ DateHelper::monthName($weekStart->month) }}
                    – {{ $weekEnd->day }} {{ DateHelper::monthName($weekEnd->month) }} {{ $weekEnd->year }}
                </div>
                <div class="text-body-tertiary" style="font-size:.75rem;">
                    {{ $weekStart->isSameWeek(DateHelper::today()) ? 'Minggu ini' : DateHelper::diffForHumans($weekStart) }}
                </div>
            </div>

            <a href="{{ route('prayer-duties.index', ['week' => $weekStart->addWeek()->toDateString()]) }}"
       wire:navigate
               class="btn btn-light"><i class="bi bi-chevron-right"></i></a>
        </div>

        <a href="{{ route('prayer-duties.index') }}"
       wire:navigate class="btn btn-light">
            <i class="bi bi-calendar-check me-1"></i> Minggu Ini
        </a>
    </div>

    <form method="POST" action="{{ route('prayer-duties.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="week_start" value="{{ $weekStart->toDateString() }}">

        <div class="table-card mb-3" data-aos="fade-up">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Hari</th>
                            @foreach ($prayers as $prayer)
                                <th class="text-center" style="min-width:190px;">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-{{ $prayer->icon() }} text-{{ $prayer->color() }}"></i>
                                        {{ $prayer->label() }}
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $date => $prayerRow)
                            @php
                                $rowDate = DateHelper::toCarbon($date);
                                $isToday = $rowDate->isSameDay(DateHelper::today());
                                $schedule = $prayerTimes->get($date);
                            @endphp

                            <tr @class(['table-active' => $isToday])>
                                <td>
                                    <div @class(['fw-semibold' => $isToday])>{{ DateHelper::dayName($rowDate) }}</div>
                                    <div class="text-body-tertiary" style="font-size:.75rem;">
                                        {{ $rowDate->day }} {{ DateHelper::monthName($rowDate->month) }}
                                        @if ($isToday)
                                            · <span class="text-primary fw-semibold">hari ini</span>
                                        @endif
                                    </div>
                                </td>

                                @foreach ($prayers as $prayer)
                                    @php $duty = $prayerRow[$prayer->value] ?? null; @endphp

                                    <td>
                                        <select name="duties[{{ $date }}][{{ $prayer->value }}][muadzin_id]"
                                                class="form-select form-select-sm"
                                                data-searchable
                                                data-placeholder="Muadzin…">
                                            <option value="">— kosong —</option>
                                            @foreach ($people as $personId => $personName)
                                                <option value="{{ $personId }}"
                                                        @selected($duty?->muadzin_id === $personId)>
                                                    {{ $personName }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @if ($schedule)
                                            <div class="text-body-tertiary text-center mt-1" style="font-size:.6875rem;">
                                                {{ $schedule->timeFor($prayer) }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2 no-print">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Simpan Jadwal Minggu Ini
            </button>
        </div>

        <p class="form-hint mt-2 no-print">
            Biarkan kosong kalau belum ada petugas — barisnya tidak akan disimpan.
        </p>
    </form>
@endsection
