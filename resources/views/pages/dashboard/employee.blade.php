@extends('layouts.app')

@section('title', 'Beranda')

@php
    use App\Support\Helpers\DateHelper;

    /** @var \App\Models\User $currentUser */
    $currentUser = auth()->user();

    // The recap query returns everyone in scope; pick out this person's row.
    $myRecap = collect($my_attendance)->firstWhere('user_id', $currentUser->id);
@endphp

@section('content')
    <x-page-header
        :title="'Assalamu\'alaikum, ' . str($currentUser->name)->before(' ')"
        subtitle="Jadwal ibadah dan kegiatan Anda hari ini." />

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            @include('partials.prayer-times-card', [
                'schedule' => $schedule,
                'nextPrayer' => $next_prayer,
                'hijriDate' => $hijri_date,
            ])
        </div>

        <div class="col-12 col-lg-7">
            <div class="row g-3">
                @if ($myRecap)
                    <div class="col-6">
                        <x-stat-card
                            label="Kehadiran Saya"
                            :value="$myRecap['rate']"
                            :decimals="1"
                            suffix="%"
                            icon="person-check"
                            :color="$myRecap['rate'] >= 75 ? 'success' : ($myRecap['rate'] >= 50 ? 'warning' : 'danger')"
                            :meta="'3 bulan terakhir'" />
                    </div>
                    <div class="col-6">
                        <x-stat-card
                            label="Kegiatan Diikuti"
                            :value="$myRecap['attended']"
                            icon="calendar-check"
                            color="info"
                            :meta="'dari ' . $myRecap['total'] . ' kegiatan'" />
                    </div>
                @endif

                <div class="col-12">
                    <div class="card" data-aos="fade-up">
                        <div class="card-body pb-2">
                            <h2 class="card-title mb-1">Kegiatan After Hours Saya</h2>
                            <p class="card-subtitle mb-3">
                                Scan QR di lokasi untuk mencatat kehadiran.
                            </p>
                        </div>

                        @forelse ($upcoming_sessions as $session)
                            <div class="d-flex align-items-center gap-3 px-4 py-3 border-top">
                                <div class="text-center flex-shrink-0" style="width:48px;">
                                    <div class="fw-bold lh-1 fs-5">{{ $session->starts_at->format('j') }}</div>
                                    <div class="text-secondary text-uppercase" style="font-size:.6875rem;">
                                        {{ DateHelper::monthName((int) $session->starts_at->format('n')) }}
                                    </div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->topic }}</div>
                                    <div class="text-secondary text-truncate" style="font-size:.8125rem;">
                                        {{ DateHelper::formatTime($session->starts_at) }}–{{ DateHelper::formatTime($session->ends_at) }}
                                        @if ($session->location) · {{ $session->location }} @endif
                                    </div>
                                    <div class="text-body-tertiary text-truncate" style="font-size:.75rem;">
                                        Mentor: {{ $session->group->mentor?->name ?? '—' }}
                                    </div>
                                </div>
                                <span class="badge {{ $session->status->badgeClass() }}">
                                    {{ $session->status->label() }}
                                </span>
                            </div>
                        @empty
                            <x-empty-state
                                icon="calendar-event"
                                title="Belum ada kegiatan"
                                text="Belum ada jadwal after hours untuk halaqah Anda. Hubungi mentor jika Anda merasa seharusnya ada." />
                        @endforelse
                    </div>
                </div>

                <div class="col-12">
                    <div class="card" data-aos="fade-up" data-aos-delay="60">
                        <div class="card-body">
                            <h2 class="card-title mb-3">Jum'at Terdekat</h2>

                            @if ($next_friday)
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ DateHelper::formatLongDate($next_friday->date) }}</div>
                                        @if ($next_friday->theme)
                                            <div class="text-secondary" style="font-size:.875rem;">
                                                {{ $next_friday->theme }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ms-auto text-end">
                                        <div class="text-secondary" style="font-size:.75rem;">Khatib</div>
                                        <div class="fw-semibold">{{ $next_friday->khatibName() }}</div>
                                    </div>
                                </div>
                            @else
                                <p class="text-secondary mb-0" style="font-size:.875rem;">
                                    Jadwal Jum'at terdekat belum diatur pengurus.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
