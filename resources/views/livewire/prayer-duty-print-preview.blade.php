{{--
    The header, the range form and the sheet.

    The header lives in here rather than in the page because both of its links
    carry the range — "Kembali" returns to the week being printed, "Cetak PDF"
    prints the range on screen — and a link outside the component would keep
    pointing at whatever range the page was first opened with.
--}}
<div>
    <x-page-header
        title="Cetak Petugas Sholat"
        subtitle="Imam dan muadzin Dzuhur dan Ashar, dicetak sebagai PDF untuk papan pengumuman.">
        <x-slot:actions>
            <a href="{{ route('prayer-duties.index', ['week' => $from]) }}"
               wire:navigate
               class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- A new tab, so this preview and its range stay here to come back to. --}}
            <a href="{{ route('prayer-duties.pdf', ['from' => $from, 'to' => $to]) }}"
               target="_blank"
               rel="noopener"
               class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- `data-no-submit-feedback`: the global busy-button handler only puts a
         label back on a page load, and this form never causes one. The spinner
         below follows the request instead. --}}
    <form wire:submit="apply" data-no-submit-feedback class="row g-2 align-items-end mb-4">
        <div class="col-6 col-md-3">
            <label for="print-from" class="form-label">Dari Tanggal</label>
            <input type="date" id="print-from" wire:model="fromInput"
                   @class(['form-control', 'is-invalid' => $errors->has('from')])>
            @error('from')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="print-to" class="form-label">Sampai Tanggal</label>
            <input type="date" id="print-to" wire:model="toInput"
                   @class(['form-control', 'is-invalid' => $errors->has('to')])>
            @error('to')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-light">
                <span wire:loading.remove wire:target="apply">
                    <i class="bi bi-funnel me-1"></i> Terapkan
                </span>
                <span wire:loading wire:target="apply">
                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Menerapkan…
                </span>
            </button>
        </div>
        <p class="col-12 form-hint mb-0">Paling panjang {{ $maxDays }} hari sekali cetak.</p>
    </form>

    {{-- Dimmed rather than emptied while the next range arrives, so the page
         keeps its height and the scroll position does not jump. --}}
    <div class="table-card" data-aos="fade-up"
         wire:loading.class="opacity-50"
         wire:target="apply">
        <div class="table-responsive">
            @include('pages.prayer-duties._sheet', [
                'grid' => $grid,
                'prayerTimes' => $prayerTimes,
                'prayers' => $prayers,
                'tableClass' => 'table align-middle mb-0',
            ])
        </div>
    </div>
</div>
