@use('App\Enums\SessionStatus')
@use('App\Support\Helpers\DateHelper')

{{--
    The month picker and the calendar — nothing else.

    The calendar is the one place a visitor reads the month: each session sits
    on its day as a card that says when, what, who and where. There used to be
    an agenda underneath repeating the same sessions, which left the visitor
    wondering which of the two to trust.
--}}
<div>
    {{-- The same toolbar as the prayer and Friday pages: which month on the
         left, the stepper that moves it on the right. --}}
    <div class="landing-toolbar" data-aos>
        <div class="landing-toolbar-picker"
             x-data="{ open: false }"
             @click.outside="open = false"
             @keydown.escape.window="open = false">

            <button type="button"
                    class="landing-toolbar-value"
                    @click="open = ! open"
                    :aria-expanded="open"
                    aria-haspopup="listbox">
                <i class="bi bi-calendar3"></i>
                <span>{{ $monthLabel }}</span>
                <i class="bi bi-chevron-down landing-toolbar-caret" :class="{ 'is-open': open }"></i>
            </button>

            {{-- `wire:ignore.self`: whether the panel is open is Alpine's inline
                 style, and a re-render from the arrows must not reset it. --}}
            <div class="landing-toolbar-panel"
                 wire:ignore.self
                 x-show="open"
                 x-cloak
                 x-transition.opacity.duration.150ms>

                <div class="landing-toolbar-years">
                    @foreach ($yearOptions as $year)
                        <button type="button"
                                class="landing-toolbar-year {{ $year === $activeYear ? 'is-active' : '' }}"
                                wire:click="selectYear({{ $year }})">
                            {{ $year }}
                        </button>
                    @endforeach
                </div>

                {{-- Months the calendar cannot open on are still listed, so the
                     year keeps its shape — just plainly out of reach. --}}
                <div class="landing-toolbar-months" role="listbox" aria-label="Pilih bulan">
                    @foreach ($monthOptions as $value => $option)
                        <button type="button"
                                role="option"
                                aria-selected="{{ $value === $this->month ? 'true' : 'false' }}"
                                class="landing-toolbar-pick {{ $value === $this->month ? 'is-active' : '' }}"
                                wire:click="selectMonth('{{ $value }}')"
                                @click="open = false"
                                @disabled(! $option['browsable'])>
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <span class="landing-toolbar-spinner">
                <span class="spinner-border spinner-border-sm"
                      wire:loading
                      wire:target="selectMonth, selectYear, previousMonth, nextMonth, resetMonth"
                      aria-hidden="true"></span>
            </span>
        </div>

        <div class="landing-toolbar-month">
            <button type="button"
                    class="landing-toolbar-step {{ $hasPreviousMonth ? '' : 'is-off' }}"
                    wire:click="previousMonth"
                    @disabled(! $hasPreviousMonth)
                    aria-label="Bulan sebelumnya">
                <i class="bi bi-chevron-left"></i>
            </button>

            <button type="button"
                    class="landing-toolbar-step {{ $hasNextMonth ? '' : 'is-off' }}"
                    wire:click="nextMonth"
                    @disabled(! $hasNextMonth)
                    aria-label="Bulan berikutnya">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    @unless ($isCurrentMonth)
        <p class="text-center text-body-tertiary small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Kalender menampilkan <strong>{{ $monthLabel }}</strong>.
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                    wire:click="resetMonth">
                Kembali ke bulan ini
            </button>
        </p>
    @endunless

    {{--
        Dimmed rather than emptied while the next month arrives, so the page keeps
        its height. The key carries the month, so the new calendar is a new
        element and plays its entrance instead of being rewritten in place.
    --}}
    <div wire:loading.class="is-fetching"
         wire:target="selectMonth, selectYear, previousMonth, nextMonth, resetMonth"
         class="landing-calendar-swap">

        <div class="landing-calendar" wire:key="calendar-{{ $this->month }}" data-aos>
            {{-- What the month holds, and what the two card colours mean. --}}
            <div class="landing-calendar-bar">
                <span class="landing-calendar-count">
                    <strong>{{ $total }}</strong> kegiatan di {{ $monthLabel }}
                </span>

                <span class="landing-calendar-legend">
                    <span><i class="landing-calendar-key"></i> Akan datang</span>
                    <span><i class="landing-calendar-key is-completed"></i> Sudah berlangsung</span>
                </span>
            </div>

            <div class="landing-calendar-head" aria-hidden="true">
                @foreach ($weekdayLabels as $label)
                    <div>{{ $label }}</div>
                @endforeach
            </div>

            <div class="landing-calendar-grid">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        @php
                            $date = $day->toDateString();
                            // dd($date);
                            $isOutside = $day->month !== $monthDate->month;
                            $daySessions = $isOutside ? collect() : $sessionsByDate->get($date, collect());
                            // dd($sessionsByDate->get('2026-09-14', collect()));
                        @endphp

                        <div @class([
                            'landing-calendar-day',
                            'is-outside' => $isOutside,
                            'is-today' => $date === $todayDate,
                            'has-sessions' => $daySessions->isNotEmpty(),
                        ])>
                            {{-- The weekday is only shown once the grid becomes a list:
                                 in the grid the column heading already says it. --}}
                            <div class="landing-calendar-dayhead">
                                <span class="landing-calendar-weekday">{{ mb_substr(DateHelper::dayName($day), 0, 3) }}</span>
                                <span class="landing-calendar-date">{{ $day->day }}</span>

                                {{-- How many sessions the day holds. A pill with the word
                                     beside the number, so it never reads as a second date —
                                     and on a busy day it says there is more to scroll to. --}}
                                @if ($daySessions->isNotEmpty())
                                    <span class="landing-calendar-daycount"><strong>{{ $daySessions->count() }}</strong> kegiatan</span>
                                @endif
                            </div>

                            @if ($daySessions->isNotEmpty())
                                <div class="landing-calendar-events">
                                    @foreach ($daySessions as $session)
                                        <a href="{{ route('portal.sessions.show', ['session' => $session->id]) }}"
                                           wire:key="event-{{ $session->id }}"
                                           @class([
                                               'landing-calendar-event',
                                               'is-completed' => $session->status === SessionStatus::Completed,
                                           ])
                                           title="{{ $session->topic }}">
                                            <span class="landing-calendar-event-time">
                                                {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }}
                                            </span>

                                            <span class="landing-calendar-event-title">{{ $session->group->name }}</span>

                                            {{-- @if ($session->mentor)
                                                <span class="landing-calendar-event-meta">
                                                    <i class="bi bi-person-badge"></i>
                                                    <span>{{ $session->mentor->name }}</span>
                                                </span>
                                            @endif --}}

                                            @if ($session->location)
                                                <span class="landing-calendar-event-meta">
                                                    <i class="bi bi-geo-alt"></i>
                                                    <span>{{ $session->location }}</span>
                                                </span>
                                            @endif

                                            @if ($session->isRescheduled())
                                                <span class="landing-calendar-event-flag">
                                                    <i class="bi bi-arrow-repeat"></i> Dijadwal ulang
                                                </span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>

            @if ($total === 0)
                <div class="landing-calendar-empty">
                    <i class="bi bi-calendar-x"></i>
                    Belum ada kegiatan yang diumumkan untuk {{ $monthLabel }}.
                </div>
            @endif
        </div>
    </div>
</div>
