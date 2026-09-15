@use('App\Enums\SessionStatus')
@use('App\Support\Helpers\DateHelper')

{{--
    The month picker, the calendar and the month's agenda — nothing else.

    Everything above this on the page is about After Hours in general, not the
    month being read, so it stays put while the visitor browses.
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
        its height. The keys carry the month, so the new calendar and agenda are
        new elements and play their entrance instead of being rewritten in place.
    --}}
    <div wire:loading.class="is-fetching"
         wire:target="selectMonth, selectYear, previousMonth, nextMonth, resetMonth"
         class="landing-calendar-swap">

        <div class="landing-calendar" wire:key="calendar-{{ $this->month }}" data-aos>
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
                            $daySessions = $day->month === $monthDate->month ? $sessionsByDate->get($date, collect()) : collect();
                        @endphp

                        <div @class([
                            'landing-calendar-day',
                            'is-outside' => $day->month !== $monthDate->month,
                            'is-today' => $date === $todayDate,
                        ])>
                            <span class="landing-calendar-date">{{ $day->day }}</span>

                            @foreach ($daySessions as $session)
                                <a href="{{ route('portal.sessions.show', ['session' => $session->id]) }}"
                                   @class([
                                       'landing-calendar-event',
                                       'is-completed' => $session->status === SessionStatus::Completed,
                                   ])
                                   title="{{ $session->starts_at->format('H:i') }} · {{ $session->topic }}">
                                    <span class="landing-calendar-event-time">{{ $session->starts_at->format('H:i') }}</span>
                                    <span class="landing-calendar-event-title">{{ $session->topic }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- The same month as a list: on a phone the calendar shows only dots,
             so this is where the sessions are actually read. --}}
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-4"
             wire:key="agenda-head-{{ $this->month }}" data-aos>
            <h2 class="landing-heading h4 mb-0">Agenda {{ $monthLabel }}</h2>
            <span class="landing-sub mb-0">{{ $total }} kegiatan</span>
        </div>

        @if ($total === 0)
            <div class="landing-empty" wire:key="empty-{{ $this->month }}" data-aos>
                <i class="bi bi-calendar-x"></i>
                <p class="mb-0">Belum ada kegiatan yang diumumkan untuk {{ $monthLabel }}.</p>
            </div>
        @else
            @foreach ($sessionsByDate as $date => $daySessions)
                @php($day = DateHelper::toCarbon($date))

                <div class="landing-day-group"
                     wire:key="day-{{ $date }}"
                     data-aos
                     data-aos-delay="{{ min(($loop->index + 1) * 60, 300) }}">

                    <div class="landing-day-head">
                        <span class="landing-day-badge">
                            <span class="landing-day-num">{{ $day->format('d') }}</span>
                            <span class="landing-day-mon">{{ DateHelper::shortMonthName((int) $day->format('n')) }}</span>
                        </span>

                        <div class="min-w-0">
                            <div class="landing-day-name">{{ DateHelper::dayName($day) }}</div>
                            <div class="landing-day-full">{{ DateHelper::formatLongDate($day) }}</div>
                        </div>

                        <span class="landing-day-count">{{ $daySessions->count() }} kegiatan</span>
                    </div>

                    <div class="landing-day-body">
                        @foreach ($daySessions as $session)
                            @include('partials.portal.session-card', ['session' => $session])
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
