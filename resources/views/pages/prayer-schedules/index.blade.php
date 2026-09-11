@extends('layouts.app')

@section('title', 'Jadwal Sholat')

@php
    use App\Support\Helpers\DateHelper;

    // `$today` from the controller is the PrayerSchedule model for today;
    // this is just its date string, used to highlight the current row.
    $todayDate = DateHelper::today()->toDateString();
    $isAdmin = auth()->user()->isAdministrator();
@endphp

@section('content')
    <x-page-header
        title="Jadwal Sholat"
        :subtitle="'Dihitung otomatis dengan metode ' . ($methods[$method] ?? $method) . '.'">
        <x-slot:actions>
            @if ($isAdmin)
                <form method="POST" action="{{ route('prayer-schedules.generate') }}">
                    @csrf
                    <button type="submit" class="btn btn-light">
                        <i class="bi bi-arrow-repeat me-1"></i> Lengkapi Jadwal
                    </button>
                </form>
                <a href="{{ route('settings.edit') }}"
       wire:navigate class="btn btn-light">
                    <i class="bi bi-sliders me-1"></i> Pengaturan Hisab
                </a>
            @endif
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            @include('partials.prayer-times-card', [
                'schedule' => $today,
                'nextPrayer' => $nextPrayer,
                'hijriDate' => DateHelper::formatHijri(),
            ])
        </div>

        <div class="col-12 col-lg-8">
            {{--
                A Livewire component, so stepping through months replaces this
                one card instead of reloading the whole page to change thirty
                rows in a table.
            --}}
            <livewire:prayer-month-table :month="request()->string('month')->toString()" :is-admin="$isAdmin" />
        </div>
    </div>
@endsection
