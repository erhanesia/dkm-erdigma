@props([
    /** Rendered height in pixels. The width follows the aspect ratio. */
    'height' => 34,

    /**
     * Whether it sits on one of the dark green surfaces.
     *
     * The wordmark's "ERDIGMA" is near-black, so on the navbar, sidebar or login
     * panel it would all but disappear. It is placed on a light chip rather than
     * being filtered to white, which would flatten the coloured hexagon into a
     * silhouette and throw away the only part of the mark that carries brand
     * colour.
     */
    'onDark' => false,

    /** `mark` uses the hexagon alone, for square spaces. */
    'variant' => 'wide',
])

@php
    $file = $variant === 'mark' ? 'logo-mark.png' : 'logo-wide.png';

    // Intrinsic sizes of the generated files, so the browser reserves the right
    // box before the image arrives and the header does not jump.
    [$naturalWidth, $naturalHeight] = $variant === 'mark' ? [256, 321] : [640, 193];

    $width = (int) round($height * ($naturalWidth / $naturalHeight));
@endphp

<span {{ $attributes->class(['brand-logo', 'brand-logo--on-dark' => $onDark]) }}>
    <img src="{{ asset('assets/'.$file) }}"
         alt="{{ config('app.name') }}"
         width="{{ $width }}"
         height="{{ $height }}"
         style="height: {{ $height }}px; width: auto;"
         decoding="async">
</span>
