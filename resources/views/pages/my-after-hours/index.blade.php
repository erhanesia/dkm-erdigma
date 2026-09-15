@use('Illuminate\Support\Str')

@extends('layouts.app')

@section('title', 'After Hours Saya')

@section('content')
    <x-page-header
        title="After Hours Saya"
        subtitle="Halaqah yang Anda ikuti, jadwal kegiatannya, dan catatan kehadiran Anda." />

    @if ($group === null)
        {{-- Not yet placed in a halaqah. Says who to ask, rather than just
             reporting emptiness. --}}
        <x-empty-state
            icon="people"
            title="Anda belum tergabung di halaqah mana pun"
            text="Pengurus DKM atau ustadz pembina yang mendaftarkan anggota ke halaqah. Hubungi mereka untuk bergabung." />

        {{-- A mentor who leads a halaqah without being a member of one still
             has its attendance to follow. --}}
        @if ($mentoredStatistics !== null)
            <div class="mt-4">
                @include('partials.attendance-statistics', [
                    'statistics' => $mentoredStatistics,
                    'title' => 'Halaqah yang Saya Bina',
                    'subtitle' => 'Status kehadiran anggota halaqah binaan Anda per bulan, tahun ' . $year,
                    'emptyText' => 'Grafik terisi setelah kegiatan halaqah binaan Anda berjalan dan presensinya dicatat.',
                    'anchor' => 'statistik-binaan',
                ])
            </div>
        @endif
    @else
        <div class="row g-3 mb-4">
            {{-- ------------------------------------------------ Halaqah --}}
            <div class="col-12 col-lg-4" data-aos>
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title mb-3">Halaqah Saya</h2>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="d-grid rounded-3 bg-primary text-white fw-bold flex-shrink-0"
                                  style="width:44px;height:44px;place-items:center;">
                                <i class="bi bi-people"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $group->name }}</div>
                                <div class="text-secondary" style="font-size:.8125rem;">{{ $group->code }}</div>
                            </div>
                        </div>

                        <dl class="row g-0 mb-0" style="font-size:.875rem;">
                            <dt class="col-5 text-secondary fw-normal py-1">Pembina</dt>
                            <dd class="col-7 py-1 mb-0 fw-semibold">{{ $group->mentor?->name ?? '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal py-1">Anggota</dt>
                            <dd class="col-7 py-1 mb-0">{{ $group->members->count() }} orang</dd>

                            @if ($group->default_location)
                                <dt class="col-5 text-secondary fw-normal py-1">Tempat</dt>
                                <dd class="col-7 py-1 mb-0">{{ $group->default_location }}</dd>
                            @endif
                        </dl>

                        @if ($group->description)
                            <p class="text-secondary mt-3 mb-0" style="font-size:.8125rem;">
                                {{ $group->description }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ------------------------------------- Kegiatan mendatang --}}
            <div class="col-12 col-lg-8" data-aos data-aos-delay="60">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title mb-3">Kegiatan Mendatang</h2>

                        @forelse ($upcoming as $session)
                            <div class="d-flex align-items-start gap-3 py-2 {{ $loop->first ? '' : 'border-top' }}">
                                <div class="text-center flex-shrink-0 rounded-3 bg-primary-subtle text-primary py-1"
                                     style="width:56px;line-height:1.2;">
                                    <div class="fw-bold">{{ $session->starts_at->format('d') }}</div>
                                    <div style="font-size:.65rem;text-transform:uppercase;">
                                        {{ \App\Support\Helpers\DateHelper::shortMonthName((int) $session->starts_at->format('n')) }}
                                    </div>
                                </div>

                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->topic }}</div>

                                    {{-- The description, not the topic again: the topic
                                         is already the line directly above. --}}
                                    @if ($session->description)
                                        <div class="text-secondary text-truncate" style="font-size:.8125rem;">
                                            {{ $session->description }}
                                        </div>
                                    @endif

                                    <div class="text-body-tertiary mt-1" style="font-size:.75rem;">
                                        <i class="bi bi-clock me-1"></i>
                                        {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }}
                                        @if ($session->location)
                                            <span class="ms-2"><i class="bi bi-geo-alt me-1"></i>{{ $session->location }}</span>
                                        @endif
                                    </div>
                                    <x-rescheduled-badge :session="$session" class="mt-1" />
                                </div>

                                <span class="badge text-bg-{{ $session->status->color() }} flex-shrink-0">
                                    {{ $session->status->label() }}
                                </span>
                            </div>
                        @empty
                            <p class="text-secondary mb-0" style="font-size:.875rem;">
                                Belum ada kegiatan terjadwal. Jadwal berikutnya akan muncul di sini.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------ Statistik kehadiran --}}
        {{-- One year filter for both cards: they are read side by side, so they
             always show the same year. --}}
        <div class="mb-4">
            @include('partials.attendance-statistics', [
                'statistics' => $personalStatistics,
                'title' => 'Kehadiran Saya',
                'subtitle' => 'Status kehadiran Anda di setiap bulan, tahun ' . $year,
                'emptyText' => 'Grafik terisi setelah Anda mengikuti kegiatan dan presensinya dicatat.',
                'anchor' => 'statistik',
            ])
        </div>

        @if ($mentoredStatistics !== null)
            <div class="mb-4">
                @include('partials.attendance-statistics', [
                    'statistics' => $mentoredStatistics,
                    'title' => 'Halaqah yang Saya Bina',
                    'subtitle' => 'Status kehadiran anggota halaqah binaan Anda per bulan, tahun ' . $year,
                    'emptyText' => 'Grafik terisi setelah kegiatan halaqah binaan Anda berjalan dan presensinya dicatat.',
                    'anchor' => 'statistik-binaan',
                    'filter' => false,
                ])
            </div>
        @endif

        {{-- ------------------------------------------ Riwayat kehadiran --}}
        <div class="card" data-aos data-aos-delay="120">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
                    <h2 class="card-title mb-0">Riwayat Kehadiran Saya</h2>

                    @php
                        $recorded = $past->filter(fn ($s) => $s->attendances->isNotEmpty());
                        $attended = $recorded->filter(fn ($s) => $s->attendances->first()?->status?->countsAsAttending());
                    @endphp

                    @if ($recorded->isNotEmpty())
                        <span class="text-secondary" style="font-size:.8125rem;">
                            Hadir <strong class="text-body">{{ $attended->count() }}</strong>
                            dari {{ $recorded->count() }} kegiatan tercatat
                        </span>
                    @endif
                </div>

                @if ($past->isEmpty())
                    <p class="text-secondary mb-0" style="font-size:.875rem;">
                        Belum ada kegiatan yang sudah berlalu.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Kegiatan</th>
                                    <th scope="col">Pemateri</th>
                                    <th scope="col" class="text-end">Kehadiran Saya</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($past as $session)
                                    @php($mine = $session->attendances->first())

                                    <tr>
                                        <td class="text-nowrap">
                                            {{ \App\Support\Helpers\DateHelper::formatDate($session->starts_at) }}
                                            <div class="text-body-tertiary" style="font-size:.75rem;">
                                                {{ $session->starts_at->format('H:i') }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $session->topic }}</div>
                                            @if ($session->description)
                                                <div class="text-secondary" style="font-size:.8125rem;">
                                                    {{ Str::limit($session->description, 80) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-secondary">
                                            {{ $session->mentor?->name ?? $session->group?->mentor?->name ?? '—' }}
                                        </td>
                                        <td class="text-end">
                                            @if ($mine?->status)
                                                <span class="badge text-bg-{{ $mine->status->color() }}">
                                                    {{ $mine->status->label() }}
                                                </span>
                                            @else
                                                {{-- Absent from the register is not the same as absent from
                                                     the session: nobody has recorded it yet. --}}
                                                <span class="text-body-tertiary" style="font-size:.8125rem;">
                                                    Belum dicatat
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
