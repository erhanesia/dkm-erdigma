<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/logo-mark.png') }}">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    {{-- Alpine lives inside Livewire's bundle; the password toggle needs it. --}}
    @livewireStyles
</head>
<body>
    <div class="auth-screen">
        {{-- Left panel: sets the tone, and states plainly what the app is for. --}}
        <aside class="auth-aside">
            <div class="position-relative">
                <div class="mb-5">
                    <x-brand-logo :height="52" on-dark />
                </div>

                <h1 class="fw-bold mb-3" style="font-size:2rem;letter-spacing:-.03em;max-width:16ch;">
                    Adzan tepat waktu, di setiap ruangan.
                </h1>
                <p class="text-white-50 mb-0" style="max-width:44ch;line-height:1.7;">
                    Satu tempat untuk mengatur jadwal sholat, audio tiap ruangan,
                    petugas Jum'at, dan kegiatan after hours, sekaligus memastikan
                    adzannya benar-benar terdengar.
                </p>
            </div>

            <div class="position-relative">
                <div class="row g-3">
                    @foreach ([
                        ['bi-broadcast', 'Pantau adzan', 'Ketahuan kalau tidak terdengar'],
                        ['bi-speaker', 'Kontrol per ruangan', 'Tilawah bisa beda tiap ruang'],
                        ['bi-people', 'Halaqah & presensi', 'Mentor dan binaannya terdata'],
                    ] as [$icon, $title, $desc])
                        <div class="col-12">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi {{ $icon }} fs-5 text-white-50 mt-1"></i>
                                <div>
                                    <div class="fw-semibold" style="font-size:.9375rem;">{{ $title }}</div>
                                    <div class="text-white-50" style="font-size:.8125rem;">{{ $desc }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>

        {{-- Right panel: the form itself. --}}
        <main class="auth-form-panel">
            <div class="w-100 animate-fade-up" style="max-width: 400px;">
                {{-- Light panel, so the wordmark needs no chip here. --}}
                <div class="d-lg-none mb-4">
                    <x-brand-logo :height="40" />
                </div>

                <h2 class="fw-bold mb-1" style="font-size:1.5rem;letter-spacing:-.02em;">Masuk</h2>
                <p class="text-secondary mb-4" style="font-size:.9375rem;">
                    Gunakan email kantor Anda untuk melanjutkan.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex gap-2 align-items-start py-2 px-3 mb-3">
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div style="font-size:.875rem;">{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               @class(['form-control', 'is-invalid' => $errors->has('email')])
                               placeholder="nama@erdigma.id"
                               autocomplete="username"
                               required
                               autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div x-data="{ show: false }" class="position-relative">
                            <input :type="show ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   @class(['form-control pe-5', 'is-invalid' => $errors->has('password')])
                                   placeholder="••••••••"
                                   autocomplete="current-password"
                                   required>
                            <button type="button"
                                    class="btn btn-link position-absolute end-0 top-0 h-100 px-3 text-secondary border-0"
                                    style="z-index:5;"
                                    @click="show = !show"
                                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1"
                               {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label" for="remember" style="font-size:.875rem;">
                            Ingat saya di perangkat ini
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" data-submitting-label="Memeriksa…">
                        Masuk <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                {{--
                    Supplied by LoginController, which reads them from the
                    database — a list written out here goes stale the moment a
                    seeder changes.
                --}}
                @if ($demoAccounts->isNotEmpty())
                    <div class="mt-4 pt-3 border-top">
                        <div class="divider-label mb-3">Akun demo</div>

                        <div class="d-grid gap-2">
                            @foreach ($demoAccounts as $demo)
                                <button type="button"
                                        class="btn btn-light btn-sm d-flex justify-content-between align-items-center gap-2"
                                        onclick="document.getElementById('email').value='{{ $demo->email }}';document.getElementById('password').value='password123';">
                                    <span class="text-start lh-sm text-truncate">
                                        <span class="d-block text-truncate" style="font-size:.8125rem;">{{ $demo->email }}</span>
                                        <span class="d-block text-secondary text-truncate" style="font-size:.6875rem;">
                                            {{ $demo->name }}@if ($demo->team_name) · {{ $demo->team_name }}@endif
                                        </span>
                                    </span>
                                    <span class="badge text-bg-light border flex-shrink-0" style="font-size:.6875rem;">
                                        {{ $demo->primaryRole()?->label() ?? 'Pengguna' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        @if ($demoOthers->isNotEmpty())
                            <details class="demo-more mt-2">
                                <summary>
                                    {{ $demoOthers->count() }} karyawan lain dari
                                    {{ $demoOthers->pluck('team_name')->unique()->count() }} team
                                </summary>

                                <div class="demo-more-list mt-2">
                                    @foreach ($demoOthers as $other)
                                        <button type="button"
                                                class="demo-more-item"
                                                onclick="document.getElementById('email').value='{{ $other->email }}';document.getElementById('password').value='password123';">
                                            <span class="text-truncate">
                                                {{ $other->name }}
                                                @if ($other->is_mentor)
                                                    <span class="badge text-bg-primary ms-1" style="font-size:.6rem;">Mentor</span>
                                                @endif
                                            </span>
                                            <span class="text-body-tertiary text-truncate">{{ $other->team_name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <p class="form-hint text-center mt-2 mb-0">
                            Klik untuk mengisi otomatis. Password semua akun:
                            <code>password123</code>
                        </p>
                    </div>
                @endif
            </div>
        </main>
    </div>

    @if ($flash = \App\Support\Helpers\Flash::pull())
        <script type="application/json" id="flash-payload">@json($flash)</script>
    @endif

    @livewireScripts
</body>
</html>
