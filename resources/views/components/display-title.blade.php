{{--
    A page title set on two lines: the first word in Playfair Display italic,
    the rest in the interface font with tight tracking.

    Splitting on the first space is what makes this work for every page without
    each one spelling out its own two lines — "Jadwal Sholat", "Khutbah Jumat",
    "After Hours", "Musholla Erdigma". A single-word title such as "Al-Qur'an"
    simply renders as one italic line, which is the same treatment the reference
    gives its wordmark.

    @param bool $stagger  Blur-rise the two lines in sequence. The front page
                          only, where the title is the first thing on screen.
--}}
@props(['stagger' => false])

@php
    $text = trim($slot);
    $lead = \Illuminate\Support\Str::before($text, ' ');
    $rest = str_contains($text, ' ') ? \Illuminate\Support\Str::after($text, ' ') : '';
@endphp

{{-- The two delays that stagger the lines live in `_immersive.scss`. --}}
<h1 {{ $attributes->class(['immersive-title', 'is-staggered' => $stagger]) }}>
    <span @class(['is-serif', 'hero-anim hero-reveal' => $stagger])>{{ $lead }}</span>

    @if ($rest !== '')
        <span @class(['is-sans', 'hero-anim hero-reveal' => $stagger])>{{ $rest }}</span>
    @endif
</h1>
