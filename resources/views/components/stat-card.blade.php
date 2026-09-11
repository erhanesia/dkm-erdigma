@props([
    'label',
    'value',
    'icon' => 'graph-up',
    'color' => 'primary',
    'meta' => null,
    'href' => null,
    'decimals' => 0,
    'suffix' => null,
    'countUp' => true,
])

@php
    $tag = $href ? 'a' : 'div';
    // Only animate plain numbers; a value like "12 / 15" would be mangled.
    $isNumeric = $countUp && is_numeric($value);
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'stat-tile text-decoration-none']) }}>

    <div class="d-flex align-items-start justify-content-between">
        <span class="stat-tile__icon text-bg-{{ $color }} bg-opacity-10 text-{{ $color }}">
            <i class="bi bi-{{ $icon }}"></i>
        </span>

        @if ($href)
            <i class="bi bi-arrow-up-right text-body-tertiary"></i>
        @endif
    </div>

    <div>
        <div class="stat-tile__label">{{ $label }}</div>
        <div class="stat-tile__value">
            @if ($isNumeric)
                <span data-count-to="{{ $value }}" data-count-decimals="{{ $decimals }}">0</span>
            @else
                {{ $value }}
            @endif
            @if ($suffix)<span class="fs-6 fw-semibold text-secondary">{{ $suffix }}</span>@endif
        </div>
    </div>

    @if ($meta)
        <div class="stat-tile__meta">{{ $meta }}</div>
    @endif
</{{ $tag }}>
