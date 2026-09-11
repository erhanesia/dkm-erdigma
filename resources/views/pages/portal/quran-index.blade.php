@extends('layouts.public')

@section('title', 'Al-Quran')
@section('description', 'Baca Al-Quran 30 juz lengkap dengan terjemahan Bahasa Indonesia, transliterasi latin, dan murottal.')

@push('head')
    @include('partials.portal.quran-font')
@endpush

@section('content')

    <x-portal-hero>

        <div class="container position-relative text-center" data-aos>
            <span class="landing-eyebrow">
                <i class="bi bi-book me-1"></i> 114 surat · 30 juz
            </span>

            <x-display-title class="mt-3">Al-Qur'an</x-display-title>

            <p class="landing-lead mx-auto text-center">
                Lengkap dengan terjemahan Bahasa Indonesia, transliterasi latin,
                dan murottal per ayat.
            </p>

            {{-- The whole list is already on the page, so searching it is
                 instant — no round trip per keystroke. --}}
            <div class="quran-search mx-auto mt-4" data-aos data-aos-delay="60">
                <i class="bi bi-search"></i>
                <input type="search"
                       class="form-control"
                       placeholder="Cari surat — nama, arti, atau nomor…"
                       autocomplete="off"
                       data-quran-search
                       aria-label="Cari surat">
            </div>
        </div>
    </x-portal-hero>

    <section class="landing-section">
        <div class="container">

            @if ($surahs->isEmpty())
                <div class="landing-empty" data-aos>
                    <i class="bi bi-wifi-off"></i>
                    <p class="mb-1"><strong>Daftar surat belum bisa dimuat</strong></p>
                    <p class="small mb-0">
                        Sumber datanya sedang tidak bisa dihubungi. Coba muat ulang beberapa saat lagi.
                    </p>
                </div>
            @else
                <p class="text-center landing-sub mb-4" data-quran-count>
                    {{ $surahs->count() }} surat
                </p>

                <div class="quran-grid" data-quran-list>
                    @foreach ($surahs as $surah)
                        <a href="{{ route('portal.quran.show', ['surah' => $surah['number']]) }}"
                           class="quran-card"
                           data-quran-item
                           data-search="{{ mb_strtolower($surah['latin'].' '.$surah['meaning'].' '.$surah['number'].' '.$surah['revealed']) }}"
                           data-aos
                           data-aos-delay="{{ min(($loop->index % 12 + 1) * 30, 300) }}">

                            <span class="quran-card-number">{{ $surah['number'] }}</span>

                            <span class="quran-card-body">
                                <span class="quran-card-latin">{{ $surah['latin'] }}</span>
                                <span class="quran-card-meta">
                                    {{ $surah['meaning'] }} · {{ $surah['verses'] }} ayat · {{ $surah['revealed'] }}
                                </span>
                            </span>

                            <span class="quran-card-arabic">{{ $surah['name'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Shown by the search when nothing matches. --}}
                <div class="landing-empty mt-4" data-quran-empty style="display:none;">
                    <i class="bi bi-search"></i>
                    <p class="mb-0">Tidak ada surat yang cocok.</p>
                </div>
            @endif
        </div>
    </section>

@endsection
