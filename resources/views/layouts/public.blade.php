{{--
    Shell for the public site at dkm.erdigma.id.

    Separate from layouts/app: that one assumes a signed-in user and carries a
    sidebar of pages a visitor cannot open. This one is a plain document with a
    navigation bar, and it never renders anything that depends on a session.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Beranda') — {{ $mosqueName }}</title>

    <meta name="description" content="@yield('description', 'Jadwal sholat, jadwal khutbah Jumat, dan kegiatan '.$mosqueName.'.')">
    <meta name="theme-color" content="#0f766e">

    {{-- Shown when the link is pasted into WhatsApp or a group chat. --}}
    <meta property="og:site_name" content="{{ $mosqueName }}">
    <meta property="og:title" content="@yield('title', $mosqueName)">
    <meta property="og:description" content="@yield('description', 'Jadwal sholat, khutbah Jumat, dan kegiatan '.$mosqueName.'.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="icon" type="image/png" href="{{ asset('assets/logo-mark.png') }}">

    {{--
        Fonts are declared in vite.config.js and served from this origin, so
        there is nothing to link here — @vite emits the face declarations.
    --}}
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @livewireStyles

    @stack('head')
</head>
<body class="landing @yield('body-class')">

{{-- ---------------------------------------------------------------- Navbar --}}
<nav class="landing-nav" data-landing-nav>
    <div class="container d-flex align-items-center justify-content-between py-3">
        {{--
            Each group carries its own ground. The bar behind them is never
            painted, so nothing ever appears across the full width of the window
            when the page scrolls past the hero.
        --}}
        <a href="{{ route('portal.home') }}"
           class="landing-nav-brand d-flex align-items-center gap-2 gap-sm-3 text-decoration-none">
            <x-brand-logo :height="32" on-dark />
        </a>

        <div class="landing-nav-actions d-flex align-items-center gap-2">
            {{-- Grouped rather than loose: the group is what becomes the pill. --}}
            <div class="landing-nav-links">
                @foreach ([
                    'portal.home' => 'Beranda',
                    'portal.prayer-schedules' => 'Jadwal Sholat',
                    'portal.quran' => "Al-Qur'an",
                    'portal.matsurat' => "Al-Ma'tsurat",
                    'portal.friday-schedules' => 'Khutbah Jumat',
                    'portal.sessions' => 'After Hours',
                ] as $route => $label)
                    <a href="{{ route($route) }}"
                       wire:navigate
                       class="landing-nav-link {{ request()->routeIs($route) ? 'is-active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{--
                Someone already signed in wants their dashboard, not the login
                form. Below lg it moves into the menu: two round buttons side by
                side read as one control that had been cut in half.
            --}}
            @auth
                <a href="{{ route('dashboard') }}" class="btn landing-nav-glass-btn d-none d-xl-inline-block">
                    <i class="bi bi-layout-sidebar-inset me-1"></i> Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-3 d-none d-xl-inline-block">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                </a>
            @endauth

            {{--
                Below lg the six labels no longer fit beside the logo and the
                button, so they move behind this. Bootstrap's offcanvas handles
                the backdrop, the Escape key and the focus trap; writing those
                again for a menu of six links would be six links' worth of
                markup and a browser's worth of behaviour to get wrong.
            --}}
            <button type="button"
                    class="landing-nav-toggle d-xl-none"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#landing-menu"
                    aria-controls="landing-menu"
                    aria-label="Buka menu">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>

</nav>

{{-- ------------------------------------------------------------ Menu ponsel --}}
<div class="offcanvas offcanvas-end landing-menu"
     tabindex="-1"
     id="landing-menu"
     aria-labelledby="landing-menu-title">

    <div class="offcanvas-header">
        <h2 class="landing-menu-title" id="landing-menu-title">Menu</h2>

        <button type="button"
                class="landing-menu-close"
                data-bs-dismiss="offcanvas"
                aria-label="Tutup menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="offcanvas-body landing-menu-body">
        @foreach ([
            'portal.home' => ['Beranda', 'house'],
            'portal.prayer-schedules' => ['Jadwal Sholat', 'calendar3'],
            'portal.quran' => ["Al-Qur'an", 'book'],
            'portal.matsurat' => ["Al-Ma'tsurat", 'journal-text'],
            'portal.friday-schedules' => ['Khutbah Jumat', 'person-video3'],
            'portal.sessions' => ['After Hours', 'people'],
        ] as $route => [$label, $icon])
            <a href="{{ route($route) }}"
               wire:navigate
               class="landing-menu-link {{ request()->routeIs($route) ? 'is-active' : '' }}">
                <i class="bi bi-{{ $icon }}"></i>
                <span>{{ $label }}</span>
                <i class="bi bi-chevron-right landing-menu-chevron"></i>
            </a>
        @endforeach

        {{-- The one action on this menu that is not navigation. --}}
        @auth
            <a href="{{ route('dashboard') }}" class="landing-menu-action">
                <i class="bi bi-layout-sidebar-inset"></i> Buka Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="landing-menu-action">
                <i class="bi bi-box-arrow-in-right"></i> Masuk ke Sistem
            </a>
        @endauth

        @if ($mosqueAddress)
            <p class="landing-menu-foot">
                <i class="bi bi-geo-alt me-1"></i> {{ $mosqueAddress }}
            </p>
        @endif
    </nav>
</div>

@yield('content')

{{-- ------------------------------------------------------------------- CTA --}}
<section class="landing-cta">
    <div class="container text-center" data-aos>
        <h2 class="landing-cta-title">Pengurus dan karyawan Erdigma</h2>
        <p class="landing-cta-sub">
            Masuk untuk mengatur jadwal, memantau perangkat, dan mencatat kehadiran kegiatan.
        </p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-light btn-lg px-4 mt-2">
                <i class="bi bi-layout-sidebar-inset me-2"></i> Buka Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-light btn-lg px-4 mt-2">
                <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Sistem
            </a>
        @endauth
    </div>
</section>

{{-- ---------------------------------------------------------------- Footer --}}
<footer class="landing-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-5">
                <div class="mb-3">
                    <x-brand-logo :height="40" on-dark />
                </div>

                @if ($mosqueAddress)
                    <p class="small mb-0 opacity-75">
                        <i class="bi bi-geo-alt me-1"></i> {{ $mosqueAddress }}
                    </p>
                @endif
            </div>

            <div class="col-6 col-md-4">
                <h3 class="landing-footer-title">Halaman</h3>
                <ul class="landing-footer-links">
                    <li><a href="{{ route('portal.prayer-schedules') }}">Jadwal Sholat</a></li>
                    <li><a href="{{ route('portal.quran') }}">Al-Qur'an</a></li>
                    <li><a href="{{ route('portal.matsurat') }}">Al-Ma'tsurat</a></li>
                    <li><a href="{{ route('portal.friday-schedules') }}">Khutbah Jumat</a></li>
                    <li><a href="{{ route('portal.sessions') }}">After Hours</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-3 small opacity-75">
                <h3 class="landing-footer-title">Tentang Jadwal</h3>
                <p class="mb-0">
                    Dihitung secara astronomis dengan metode Kemenag RI,
                    lalu dicocokkan dengan jadwal resmi daerah setempat.
                </p>
            </div>
        </div>

        <hr class="border-light opacity-25 my-4">

        <p class="small mb-0 opacity-75 text-center">
            © {{ now()->year }} DKM Erdigma
        </p>
    </div>
</footer>

@livewireScripts

@stack('scripts')
</body>
</html>
