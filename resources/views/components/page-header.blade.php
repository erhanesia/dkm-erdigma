@props([
    'title',
    'subtitle' => null,
])

<div class="page-header">
    <div>
        <h1 class="page-header__title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="d-flex flex-wrap gap-2 no-print">
            {{ $actions }}
        </div>
    @endif
</div>
