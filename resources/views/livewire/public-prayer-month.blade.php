@use('App\Support\Helpers\DateHelper')

{{--
    The toolbar and the month table, and nothing else.

    Changing the region used to reload the whole page. Everything above this —
    the hero, the countdown, today's strip — is about the mosque itself and does
    not change when a visitor looks up another kabupaten, so it stays put.
--}}
<div>
    {{--
        One toolbar, not two stacked bars.

        Region and month answer the same question — "which schedule am I looking
        at" — so they belong on one row.
    --}}
    <div class="landing-toolbar" data-aos>
        {{--
            Tom Select builds its own DOM around this select, which Livewire's
            morph would otherwise tear down on every re-render. The list is 518
            fixed options that never change, so there is nothing here worth
            re-rendering: it is handed over once and left alone.
        --}}
        <div class="landing-toolbar-city" wire:ignore>
            <i class="bi bi-geo-alt"></i>

            <select wire:model.live="city"
                    data-searchable
                    data-placeholder="Cari kabupaten/kota…"
                    aria-label="Pilih kabupaten atau kota">
                @foreach ($cities as $city)
                    <option value="{{ $city['id'] }}" @selected($city['id'] === $this->city)>
                        {{ $city['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="landing-toolbar-month">
            <button type="button"
                    class="landing-toolbar-step {{ $hasPreviousMonth ? '' : 'is-off' }}"
                    wire:click="previousMonth"
                    @disabled(! $hasPreviousMonth)
                    aria-label="Bulan sebelumnya">
                <i class="bi bi-chevron-left"></i>
            </button>

            <span class="landing-toolbar-title">
                {{ DateHelper::monthName((int) $monthDate->format('n')) }}
                {{ $monthDate->format('Y') }}

                {{-- Fetching another kabupaten is a network round trip; saying
                     so beats a table that sits still for a moment. --}}
                <span class="spinner-border spinner-border-sm ms-1 align-middle"
                      wire:loading
                      wire:target="city, previousMonth, nextMonth, resetCity"
                      aria-hidden="true"></span>
            </span>

            <button type="button"
                    class="landing-toolbar-step {{ $hasNextMonth ? '' : 'is-off' }}"
                    wire:click="nextMonth"
                    @disabled(! $hasNextMonth)
                    aria-label="Bulan berikutnya">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    @unless ($isHomeCity)
        <p class="text-center text-body-tertiary small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Tabel menampilkan <strong>{{ $cityLabel }}</strong>, hitung mundur di atas
            tetap {{ $mosqueName }}.
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                    wire:click="resetCity">
                Kembali ke daerah masjid
            </button>
        </p>
    @endunless

    {{--
        Dimmed rather than emptied while the next month arrives, so the page
        keeps its height and the scroll position does not jump.

        `wire:key` carries the region and the month. Livewire morphs matching
        nodes in place, which would swap thirty cells of text with no sense of
        anything having happened; a key that changes makes it replace the block
        instead, and a replaced element runs its entrance animation again.
    --}}
    <div wire:loading.class="is-fetching"
         wire:target="city, previousMonth, nextMonth, resetCity"
         class="landing-table-swap">

        @if (empty($rows))
            <div class="landing-empty" wire:key="empty-{{ $this->city }}-{{ $this->month }}">
                <i class="bi bi-calendar-x"></i>
                <p class="mb-0">Jadwal untuk bulan ini belum tersedia.</p>
            </div>
        @else
            {{-- Scrolls inside itself on a phone; the page never scrolls sideways. --}}
            <div class="landing-table-wrap"
                 wire:key="table-{{ $this->city }}-{{ $this->month }}">
                <table class="table landing-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="landing-table-date">Tanggal</th>
                            @foreach ($prayers as $prayer)
                                <th scope="col" class="text-center">{{ $prayer->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $date => $timings)
                            @php
                                $rowDate = DateHelper::toCarbon($date);
                                $isToday = $date === $todayDate;
                            @endphp

                            <tr class="{{ $isToday ? 'is-today' : '' }}">
                                <th scope="row" class="landing-table-date">
                                    <span class="landing-table-day">{{ $rowDate->format('d') }}</span>
                                    <span class="landing-table-weekday">
                                        {{ DateHelper::dayName($rowDate) }}
                                    </span>
                                    @if ($isToday)
                                        <span class="badge text-bg-primary ms-1">Hari ini</span>
                                    @endif
                                </th>

                                @foreach ($prayers as $prayer)
                                    <td class="text-center">{{ $timings[$prayer->value] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
