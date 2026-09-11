@extends('layouts.public')

@section('title', 'Surat '.$surah['latin'])
@section('description', 'Surat '.$surah['latin'].' ('.$surah['meaning'].') — '.$surah['verses'].' ayat, lengkap dengan terjemahan Bahasa Indonesia dan murottal.')

@push('head')
    @include('partials.portal.quran-font')
@endpush

@section('content')

    <x-portal-hero>

        <div class="container position-relative">
            <a href="{{ route('portal.quran') }}" class="landing-back">
                <i class="bi bi-arrow-left me-1"></i> Semua surat
            </a>

            <div class="text-center mt-3" data-aos>
                <div class="quran-hero-arabic">{{ $surah['name'] }}</div>

                <x-display-title class="mt-2">{{ $surah['latin'] }}</x-display-title>

                <p class="landing-lead mx-auto text-center mb-0">
                    {{ $surah['meaning'] }} · {{ $surah['verses'] }} ayat · {{ $surah['revealed'] }}
                </p>

                @if ($surah['audio'])
                    <div class="quran-hero-audio mt-4" data-aos data-aos-delay="60">
                        <audio controls preload="none" src="{{ $surah['audio'] }}"></audio>
                        @if ($reciter)
                            <span class="quran-reciter">Murottal: {{ $reciter }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </x-portal-hero>

    <section class="landing-section">
        <div class="container">

            {{-- Every surah but At-Taubah opens with the basmalah, and Al-Fatihah
                 carries it as its own first verse. Printing it again in either
                 case would be wrong. --}}
            @if (! in_array($surah['number'], [1, 9], true))
                <p class="quran-basmalah" data-aos>بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ</p>
            @endif

            <div class="quran-verses">
                @foreach ($surah['ayahs'] as $ayah)
                    <article class="quran-verse"
                             id="ayat-{{ $ayah['number'] }}"
                             data-aos
                             data-aos-delay="{{ min(($loop->index % 8 + 1) * 40, 300) }}">

                        <div class="quran-verse-head">
                            <span class="quran-verse-number">{{ $ayah['number'] }}</span>

                            <div class="quran-verse-tools">
                                @if ($ayah['audio'])
                                    <button type="button"
                                            class="quran-tool"
                                            data-quran-play="{{ $ayah['audio'] }}"
                                            title="Putar ayat ini"
                                            aria-label="Putar ayat {{ $ayah['number'] }}">
                                        <i class="bi bi-play-fill"></i>
                                    </button>
                                @endif

                                <button type="button"
                                        class="quran-tool"
                                        data-quran-copy="{{ $ayah['number'] }}"
                                        title="Salin ayat"
                                        aria-label="Salin ayat {{ $ayah['number'] }}">
                                    <i class="bi bi-clipboard"></i>
                                </button>

                                <a href="#ayat-{{ $ayah['number'] }}"
                                   class="quran-tool"
                                   title="Tautan ke ayat ini"
                                   aria-label="Tautan ayat {{ $ayah['number'] }}">
                                    <i class="bi bi-link-45deg"></i>
                                </a>
                            </div>
                        </div>

                        <p class="quran-arabic" dir="rtl" lang="ar" data-quran-arabic>{{ $ayah['arabic'] }}</p>

                        <p class="quran-latin" data-quran-latin>{{ $ayah['latin'] }}</p>

                        <p class="quran-translation" data-quran-translation>{{ $ayah['translation'] }}</p>
                    </article>
                @endforeach
            </div>

            {{-- ------------------------------------------------- Navigasi --}}
            <nav class="quran-nav mt-5" data-aos>
                @if ($surah['previous'])
                    <a href="{{ route('portal.quran.show', ['surah' => $surah['previous']['number']]) }}"
                       class="quran-nav-link" rel="prev">
                        <i class="bi bi-chevron-left"></i>
                        <span>
                            <small>Sebelumnya</small>
                            {{ $surah['previous']['latin'] }}
                        </span>
                    </a>
                @else
                    <span></span>
                @endif

                @if ($surah['next'])
                    <a href="{{ route('portal.quran.show', ['surah' => $surah['next']['number']]) }}"
                       class="quran-nav-link text-end" rel="next">
                        <span>
                            <small>Berikutnya</small>
                            {{ $surah['next']['latin'] }}
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                @else
                    <span></span>
                @endif
            </nav>
        </div>
    </section>

    {{-- One player for the whole page. Playing a verse moves this element rather
         than creating another, so two verses can never sound at once. --}}
    <div class="quran-player" data-quran-player hidden>
        <div class="container d-flex align-items-center gap-3">
            <button type="button" class="quran-player-stop" data-quran-stop aria-label="Hentikan">
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="min-w-0 flex-grow-1">
                <div class="quran-player-title">
                    {{ $surah['latin'] }} <span data-quran-player-ayah></span>
                </div>
                <audio controls preload="none" class="w-100" data-quran-audio></audio>
            </div>
        </div>
    </div>

@endsection
