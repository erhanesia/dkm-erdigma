@extends('layouts.public')

@section('title', 'Beranda')
@section('description', 'Jadwal sholat hari ini, jadwal khutbah Jumat, dan kegiatan after hours '.$mosqueName.'.')

@section('content')

    {{--
        The only full-height hero on the site. Every other page opens with the
        same shell sized to its contents.
    --}}
    <x-portal-hero full>
        <div class="immersive-heading">
            @if ($mosqueAddress)
                <span class="landing-eyebrow hero-anim hero-fade" style="animation-delay: 0.15s">
                    <i class="bi bi-geo-alt-fill me-1"></i> {{ $mosqueAddress }}
                </span>
            @endif

            {{--
                Fixed, not `$mosqueName`. That setting names the room the prayers
                are held in ("Musholla Erdigma") and belongs in the schedule and
                the announcements; the front page is the organisation's, and it
                is the organisation a visitor arrives looking for.
            --}}
            <x-display-title stagger class="mt-3">DKM Erdigma</x-display-title>
        </div>

        {{-- Bottom left: what this place is. --}}
        <div class="immersive-corner immersive-corner--left hero-anim hero-fade"
             style="animation-delay: 0.7s">
            <p class="immersive-note">
                Jadwal sholat dihitung otomatis setiap hari dan disiarkan langsung
                ke pengeras suara. Khutbah Jumat dan kegiatan after hours juga
                diumumkan di sini, terbuka untuk umum.
            </p>
        </div>

        {{--
            Bottom right: the next prayer.

            The reference puts a paragraph and a button here. This is the reason
            most people open a mosque page, so it takes the position instead —
            same partial as the monthly schedule uses, countdown and all.
        --}}
        <div class="immersive-corner immersive-corner--right hero-anim hero-fade"
             style="animation-delay: 0.85s">
            @include('partials.portal.next-prayer')

            <div class="immersive-actions">
                <a href="{{ route('portal.prayer-schedules') }}"
                   wire:navigate
                   class="immersive-btn immersive-btn--solid">
                    <i class="bi bi-calendar3 me-1"></i> Jadwal Sebulan
                </a>
                <a href="{{ route('portal.friday-schedules') }}"
                   wire:navigate
                   class="immersive-btn immersive-btn--ghost">
                    <i class="bi bi-person-video3 me-1"></i> Khutbah Jumat
                </a>
            </div>
        </div>

        <a href="#hari-ini" class="immersive-scroll" aria-label="Lihat jadwal hari ini">
            <i class="bi bi-chevron-down"></i>
        </a>
    </x-portal-hero>

    {{-- ------------------------------------------------------ Hari ini --}}
    <section class="landing-section" id="hari-ini">
        <div class="container">
            <div class="text-center mb-5" data-aos>
                <h2 class="landing-heading">Jadwal Sholat Hari Ini</h2>
                <p class="landing-sub">
                    {{ \App\Support\Helpers\DateHelper::formatLongDate($schedule->date) }}
                </p>
            </div>

            @include('partials.portal.prayer-strip', ['timings' => $timings, 'nextPrayer' => $nextPrayer])

            <div class="text-center mt-4" data-aos>
                <a href="{{ route('portal.prayer-schedules') }}" class="btn btn-outline-primary">
                    Lihat jadwal satu bulan <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------- Jumat & after hours --}}
    <section class="landing-section landing-section-muted">
        <div class="container">
            <div class="row g-5">

                {{-- Khutbah Jumat --}}
                <div class="col-lg-6" data-aos>
                    <div class="d-flex align-items-baseline justify-content-between mb-4">
                        <h2 class="landing-heading h4 mb-0">Khutbah Jumat</h2>
                        <a href="{{ route('portal.friday-schedules') }}" class="small text-decoration-none">
                            Semua <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    @forelse ($upcomingFridays as $friday)
                        @include('partials.portal.friday-card', [
                            'friday' => $friday,
                            'isFeatured' => $loop->first,
                        ])
                    @empty
                        <div class="landing-empty">
                            <i class="bi bi-calendar-x"></i>
                            <p class="mb-0">Jadwal Jumat berikutnya belum diumumkan.</p>
                        </div>
                    @endforelse
                </div>

                {{-- After hours --}}
                <div class="col-lg-6" data-aos data-aos-delay="120">
                    <div class="d-flex align-items-baseline justify-content-between mb-4">
                        <h2 class="landing-heading h4 mb-0">After Hours Terdekat</h2>
                        <a href="{{ route('portal.sessions') }}" class="small text-decoration-none">
                            Semua <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    @forelse ($upcomingSessions as $session)
                        @include('partials.portal.session-card', ['session' => $session])
                    @empty
                        <div class="landing-empty">
                            <i class="bi bi-calendar-x"></i>
                            <p class="mb-0">Belum ada kegiatan yang diumumkan.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------- Apa isinya --}}
    <section class="landing-section">
        <div class="container">
            <div class="text-center mb-5" data-aos>
                <h2 class="landing-heading">Yang Dikelola Sistem Ini</h2>
                <p class="landing-sub">
                    Dibangun untuk menjawab persoalan nyata di lapangan,
                    bukan sekadar mencatat jadwal.
                </p>
            </div>

            <div class="row g-4">
                @foreach ([
                    ['bi-broadcast-pin', 'Adzan Otomatis & Terpantau',
                     'Adzan diputar sendiri sesuai jadwal. Sistem juga membaca level suara yang benar-benar keluar, jadi ketika adzan tidak terdengar, pengurus tahu lebih dulu sebelum ada yang mengeluh.'],
                    ['bi-arrow-clockwise', 'Pulih Sendiri Setelah Mati Listrik',
                     'Pemutar berjalan sebagai layanan sistem, sehingga hidup kembali otomatis begitu perangkat menyala. Tidak perlu ada yang datang menyalakan ulang.'],
                    ['bi-sliders', 'Pengaturan per Ruangan',
                     'Setiap ruangan punya pengaturannya sendiri: adzan tetap menyala, murottal bisa dimatikan di ruangan yang butuh tenang.'],
                    ['bi-calendar-week', 'Jadwal Jumat Tertata',
                     'Khatib, imam, dan muadzin dijadwalkan jauh hari. Satu orang boleh merangkap, dan pekan yang belum terisi ditandai otomatis.'],
                    ['bi-people', 'Pembinaan Karyawan',
                     'Setiap ustadz punya kelompok binaan yang jelas, lengkap dengan jadwal pertemuannya.'],
                    ['bi-qr-code-scan', 'Presensi Kegiatan',
                     'Kehadiran kegiatan after hours dicatat lewat pemindaian QR, lalu terangkum sendiri menjadi laporan.'],
                ] as $index => [$icon, $title, $body])
                    <div class="col-md-6 col-lg-4"
                         data-aos
                         data-aos-delay="{{ min(($index + 1) * 60, 300) }}">
                        <div class="landing-feature h-100">
                            <span class="landing-feature-icon">
                                <i class="bi {{ $icon }}"></i>
                            </span>
                            <h3 class="landing-feature-title">{{ $title }}</h3>
                            <p class="landing-feature-body mb-0">{{ $body }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

@endsection
