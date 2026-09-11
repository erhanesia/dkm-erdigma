@props([
    'action',
    'placeholder' => 'Cari…',
    'searchKey' => 'filter[search]',
])

{{-- Filters live in the query string, so a filtered view stays shareable and
     the browser back button behaves as people expect. --}}
<form method="GET" action="{{ $action }}" class="row g-2 align-items-end mb-3 no-print">
    <div class="col-12 col-md">
        <div class="position-relative">
            <i class="bi bi-search position-absolute top-50 translate-middle-y text-secondary"
               style="left:.75rem;"></i>
            <input type="search"
                   name="{{ $searchKey }}"
                   value="{{ request()->input('filter.search') }}"
                   class="form-control ps-5"
                   placeholder="{{ $placeholder }}">
        </div>
    </div>

    {{ $slot }}

    <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary" data-submitting-label="Menerapkan…">
            <i class="bi bi-funnel me-1"></i> Terapkan
        </button>

        @if (request()->hasAny(['filter', 'sort']))
            <a href="{{ $action }}" class="btn btn-light" title="Bersihkan filter">
                <i class="bi bi-x-lg"></i>
            </a>
        @endif
    </div>
</form>
