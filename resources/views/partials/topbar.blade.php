@php
    /** @var \App\Models\User $currentUser */
    $currentUser = auth()->user();
@endphp

<header class="app-topbar">
    <button type="button" class="btn btn-sm btn-light d-lg-none" data-sidebar-toggle
            aria-label="Buka menu">
        <i class="bi bi-list fs-5"></i>
    </button>

    <div class="d-none d-md-flex align-items-center gap-2 text-secondary">
        <i class="bi bi-calendar3"></i>
        <span class="small">{{ \App\Support\Helpers\DateHelper::formatLongDate() }}</span>
        <span class="text-body-tertiary">·</span>
        {{-- Moved here from the sidebar brand block, which is now the logo on
             its own. This is the date area, which is where it belonged. --}}
        <span class="small d-none d-lg-inline">{{ \App\Support\Helpers\DateHelper::formatHijri() }}</span>
        <span class="text-body-tertiary d-none d-lg-inline">·</span>
        <span class="small fw-semibold text-tabular" data-clock
              data-server-time="{{ now()->toIso8601String() }}">--:--:--</span>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
        {{--
            Back to the public site. Kept as its own button rather than buried in
            the dropdown because pengurus edit a schedule and then want to check
            how it reads to a visitor — that round trip happens often enough to
            deserve one click.

            Opens in a new tab: the panel is a workspace, and losing your place
            in it to look at the public page would be worse than a second tab.
        --}}
        <a href="{{ route('portal.home') }}"
           target="_blank"
           rel="noopener"
           class="btn btn-sm btn-light d-flex align-items-center gap-2 py-2 px-2 px-sm-3"
           title="Buka halaman publik di tab baru">
            <i class="bi bi-globe2"></i>
            <span class="d-none d-md-inline" style="font-size:.8125rem;">Halaman Publik</span>
            <i class="bi bi-box-arrow-up-right text-secondary d-none d-md-inline" style="font-size:.65rem;"></i>
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-light d-flex align-items-center gap-2 py-2 px-2 px-sm-3"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <span class="d-grid place-items-center rounded-circle bg-primary text-white fw-bold"
                      style="width:30px;height:30px;font-size:.75rem;place-items:center;">
                    {{ $currentUser->initials() }}
                </span>
                <span class="d-none d-sm-block text-start lh-sm">
                    <span class="d-block fw-semibold" style="font-size:.8125rem;">{{ $currentUser->name }}</span>
                    <span class="d-block text-secondary" style="font-size:.6875rem;">
                        {{ $currentUser->primaryRole()?->label() ?? 'Pengguna' }}
                    </span>
                </span>
                <i class="bi bi-chevron-down text-secondary" style="font-size:.7rem;"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end mt-2" style="min-width: 240px;">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ $currentUser->name }}</div>
                    <div class="small text-secondary text-truncate">{{ $currentUser->email }}</div>
                    @if ($currentUser->jobTitle())
                        <div class="small text-body-tertiary text-truncate">{{ $currentUser->jobTitle() }}</div>
                    @endif
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person"></i> Profil Saya
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('player.show') }}"
                       target="_blank">
                        <i class="bi bi-broadcast"></i> Layar Player
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-box-arrow-right"></i> Keluar
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
