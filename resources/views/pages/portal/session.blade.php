@extends('layouts.public')

@section('title', $session->topic)

@section('description', $session->topic
    ?: \App\Support\Helpers\DateHelper::formatLongDate($session->starts_at).' · '.$session->starts_at->format('H:i').' WIB')

@section('content')

    <x-portal-hero>

        <div class="container position-relative">
            {{-- Trail back to the calendar, open on this session's month. A detail
                 page reached from a shared link has no history to go back through. --}}
            <a href="{{ route('portal.sessions', ['bulan' => $session->starts_at->format('Y-m')]) }}" class="landing-back">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke kalender
            </a>

            <span class="landing-eyebrow mt-3">
                <i class="bi bi-calendar-event me-1"></i>
                {{ \App\Support\Helpers\DateHelper::formatLongDate($session->starts_at) }}
            </span>

            <x-display-title class="mt-3">{{ $session->topic }}</x-display-title>

            <p class="landing-lead">
                Kegiatan after hours{{ $session->group ? ' — '.$session->group->name : '' }}
            </p>
        </div>
    </x-portal-hero>

    <section class="landing-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8" data-aos>
                    <div class="landing-detail">
                        <h2 class="landing-heading h4 mb-4">Keterangan</h2>

                        <dl class="landing-facts">
                            <dt><i class="bi bi-calendar3"></i> Tanggal</dt>
                            <dd>{{ \App\Support\Helpers\DateHelper::formatLongDate($session->starts_at) }}</dd>

                            <dt><i class="bi bi-clock"></i> Waktu</dt>
                            <dd>
                                {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }} WIB
                                <span class="d-block text-body-tertiary" style="font-size:.8125rem;">
                                    {{ $session->starts_at->diffInMinutes($session->ends_at) }} menit
                                </span>
                            </dd>

                            @if ($session->mentor)
                                <dt><i class="bi bi-person-badge"></i> Pemateri</dt>
                                <dd>{{ $session->mentor->name }}</dd>
                            @endif

                            @if ($session->location)
                                <dt><i class="bi bi-geo-alt"></i> Tempat</dt>
                                <dd>{{ $session->location }}</dd>
                            @endif

                            <dt><i class="bi bi-info-circle"></i> Status</dt>
                            <dd>
                                <span class="badge text-bg-{{ $session->status->color() }}">
                                    {{ $session->status->label() }}
                                </span>
                            </dd>
                        </dl>

                        @if ($session->description)
                            <div class="landing-prose mt-4">
                                <h3 class="landing-heading h5 mb-2">Tentang Kegiatan</h3>
                                {{-- Plain text from a form field, so newlines are all
                                     that need turning into markup. --}}
                                <p class="mb-0">{!! nl2br(e($session->description)) !!}</p>
                            </div>
                        @endif

                    </div>

                    {{--
                        The roster. Names and roles only — attendance is not
                        loaded at all, because "belongs to this halaqah" is an
                        announcement and "did not turn up" is not.
                    --}}
                    @php($participants = $session->group?->members ?? collect())

                    <div class="landing-detail mt-4" data-aos data-aos-delay="60">
                        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-4">
                            <h2 class="landing-heading h4 mb-0">Peserta</h2>
                            <span class="text-body-tertiary" style="font-size:.8125rem;">
                                {{ $participants->count() }} orang
                            </span>
                        </div>

                        @if ($participants->isEmpty())
                            <p class="text-body-tertiary mb-0" style="font-size:.875rem;">
                                Belum ada peserta terdaftar.
                            </p>
                        @else
                            <div class="landing-people">
                                @if ($session->group?->mentor)
                                    <div class="landing-person is-mentor">
                                        <span class="landing-person-avatar">
                                            {{ \Illuminate\Support\Str::of($session->group->mentor->name)->substr(0, 1)->upper() }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="landing-person-name">{{ $session->group->mentor->name }}</span>
                                            <span class="landing-person-role">Pembina</span>
                                        </span>
                                    </div>
                                @endif

                                @foreach ($participants as $person)
                                    <div class="landing-person">
                                        <span class="landing-person-avatar">
                                            {{ \Illuminate\Support\Str::of($person->name)->substr(0, 1)->upper() }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="landing-person-name">{{ $person->name }}</span>
                                            @if ($person->position_name || $person->team_name)
                                                <span class="landing-person-role">
                                                    {{ collect([$person->position_name, $person->team_name])->filter()->implode(' · ') }}
                                                </span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <p class="text-body-tertiary mt-3 mb-0" style="font-size:.75rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                Catatan kehadiran tidak ditampilkan di halaman publik.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- ---------------------------------------- Kegiatan lain --}}
                <div class="col-lg-4" data-aos data-aos-delay="120">
                    <div class="landing-aside">
                        <h2 class="landing-heading h5 mb-3">Kegiatan Berikutnya</h2>

                        @forelse ($others as $other)
                            <a href="{{ route('portal.sessions.show', ['session' => $other->id]) }}"
                               class="landing-aside-item">
                                <span class="landing-aside-date">
                                    {{ $other->starts_at->format('d') }}
                                    {{ \App\Support\Helpers\DateHelper::shortMonthName((int) $other->starts_at->format('n')) }}
                                    · {{ $other->starts_at->format('H:i') }}
                                </span>
                                <span class="landing-aside-title">{{ $other->topic }}</span>
                                @if ($other->mentor)
                                    <span class="landing-aside-meta">{{ $other->mentor->name }}</span>
                                @endif
                            </a>
                        @empty
                            <p class="text-body-tertiary mb-0" style="font-size:.875rem;">
                                Belum ada kegiatan berikutnya.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
