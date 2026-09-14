@extends('layouts.app')

@section('title', 'Cetak Petugas Sholat')

@section('content')
    <x-page-header
        title="Cetak Petugas Sholat"
        subtitle="Imam dan muadzin Dzuhur dan Ashar, dicetak sebagai PDF untuk papan pengumuman.">
        <x-slot:actions>
            <a href="{{ route('prayer-duties.index', ['week' => $from->toDateString()]) }}"
               wire:navigate
               class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- A new tab, so this preview and its range stay here to come back to. --}}
            <a href="{{ route('prayer-duties.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               target="_blank"
               rel="noopener"
               class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-6 col-md-3">
            <label for="print-from" class="form-label">Dari Tanggal</label>
            <input type="date" id="print-from" name="from" value="{{ $from->toDateString() }}"
                   @class(['form-control', 'is-invalid' => $errors->has('from')])>
            @error('from')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="print-to" class="form-label">Sampai Tanggal</label>
            <input type="date" id="print-to" name="to" value="{{ $to->toDateString() }}"
                   @class(['form-control', 'is-invalid' => $errors->has('to')])>
            @error('to')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-light" data-submitting-label="Menerapkan…">
                <i class="bi bi-funnel me-1"></i> Terapkan
            </button>
        </div>
        <p class="col-12 form-hint mb-0">Paling panjang {{ $maxDays }} hari sekali cetak.</p>
    </form>

    <div class="table-card" data-aos="fade-up">
        <div class="table-responsive">
            @include('pages.prayer-duties._sheet', [
                'grid' => $grid,
                'prayerTimes' => $prayerTimes,
                'prayers' => $prayers,
                'tableClass' => 'table align-middle mb-0',
            ])
        </div>
    </div>
@endsection
