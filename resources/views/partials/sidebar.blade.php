@php
    use App\Enums\UserRole;

    /** @var \App\Models\User $currentUser */
    $currentUser = auth()->user();
    $isAdmin = $currentUser->isAdministrator();
    $isMentor = $currentUser->isMentor();
@endphp

<aside class="app-sidebar" id="app-sidebar">
    <a href="{{ route('dashboard') }}"
       wire:navigate class="app-sidebar__brand">
        <x-brand-logo :height="42" on-dark class="w-100 justify-content-center" />
    </a>

    <nav class="app-nav">
        <x-nav-link :href="route('dashboard')" icon="grid-1x2" :active="request()->routeIs('dashboard')">
            Dashboard
        </x-nav-link>

        <div class="app-sidebar__section">Ibadah</div>

        <x-nav-link :href="route('prayer-schedules.index')" icon="clock-history"
                    :active="request()->routeIs('prayer-schedules.*')">
            Jadwal Sholat
        </x-nav-link>

        <x-nav-link :href="route('friday-schedules.index')" icon="calendar-week"
                    :active="request()->routeIs('friday-schedules.*')">
            Jadwal Jum'at
        </x-nav-link>

        @if ($isAdmin)
            <x-nav-link :href="route('prayer-duties.index')" icon="person-badge"
                        :active="request()->routeIs('prayer-duties.*')">
                Petugas Sholat
            </x-nav-link>
        @endif

        @if ($isAdmin)
            <div class="app-sidebar__section">Audio &amp; Perangkat</div>

            <x-nav-link :href="route('audio-zones.index')" icon="speaker"
                        :active="request()->routeIs('audio-zones.*')">
                Zona Audio
            </x-nav-link>

            <x-nav-link :href="route('murottal-schedules.index')" icon="music-note-list"
                        :active="request()->routeIs('murottal-schedules.*')">
                Jadwal Tilawah
            </x-nav-link>

            <x-nav-link :href="route('audio-tracks.index')" icon="file-earmark-music"
                        :active="request()->routeIs('audio-tracks.*')">
                Pustaka Audio
            </x-nav-link>

            <x-nav-link :href="route('devices.index')" icon="pc-display"
                        :active="request()->routeIs('devices.*')">
                Perangkat
            </x-nav-link>

            <x-nav-link :href="route('playback-logs.index')" icon="activity"
                        :active="request()->routeIs('playback-logs.*')"
                        :badge="$unresolvedPlaybackIssues ?? null">
                Monitoring
            </x-nav-link>
        @endif

        {{--
            Members get their own read-only view. Placed above the management
            block because an employee — the largest group of users — otherwise
            saw no After Hours menu at all and had no way to check their halaqah
            or the next kajian.
        --}}
        <div class="app-sidebar__section">After Hours</div>

        <x-nav-link :href="route('my-after-hours.index')" icon="calendar-heart"
                    :active="request()->routeIs('my-after-hours.*')">
            After Hours Saya
        </x-nav-link>

        @if ($isAdmin || $isMentor)

            <x-nav-link :href="route('mentoring-groups.index')" icon="people"
                        :active="request()->routeIs('mentoring-groups.*')">
                Halaqah
            </x-nav-link>

            <x-nav-link :href="route('sessions.index')" icon="calendar-event"
                        :active="request()->routeIs('sessions.*')">
                Kegiatan
            </x-nav-link>

            <x-nav-link :href="route('reports.attendance')" icon="clipboard-data"
                        :active="request()->routeIs('reports.*')">
                Laporan Kehadiran
            </x-nav-link>
        @endif

        @if ($isAdmin)
            <div class="app-sidebar__section">Sistem</div>

            <x-nav-link :href="route('users.index')" icon="person-gear"
                        :active="request()->routeIs('users.*')">
                Pengguna
            </x-nav-link>

            <x-nav-link :href="route('settings.edit')" icon="sliders"
                        :active="request()->routeIs('settings.edit')">
                Pengaturan
            </x-nav-link>

            <x-nav-link :href="route('settings.activity-log')" icon="journal-text"
                        :active="request()->routeIs('settings.activity-log')">
                Log Aktivitas
            </x-nav-link>
        @endif
    </nav>

    <div class="mt-auto p-3">
        <div class="rounded-3 p-3" style="background: rgba(255,255,255,.08);">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-broadcast text-white-50"></i>
                <span class="small fw-semibold text-white">Layar Player</span>
            </div>
            <p class="mb-2 text-white-50" style="font-size:.75rem; line-height:1.5;">
                Buka halaman ini di perangkat tiap ruangan untuk memutar adzan.
            </p>
            <a href="{{ route('player.show') }}" target="_blank"
               class="btn btn-sm btn-light w-100 fw-semibold">
                Buka Player <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>
        </div>
    </div>
</aside>
