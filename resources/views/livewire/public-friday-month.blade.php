@use('App\Support\Helpers\DateHelper')

{{--
    The month picker and the month's cards, and nothing else.

    Stepping to another month used to reload the whole page. Everything above
    this — the hero and the Friday it counts down to — is about the mosque
    rather than the month being read, so it stays put.
--}}
<div>
    {{--
        One toolbar, laid out like the prayer page's: the control that says
        *which* month on the left, the stepper that moves it on the right.

        The two arrows stay next to each other rather than flanking a label —
        stepping through a roster is a repeated click, and a pair is one target
        to come back to instead of two on opposite sides of the bar.
    --}}
    <div class="landing-toolbar" data-aos>
        {{--
            A month picker of the site's own making rather than the browser's.

            A native `<select>` opens the operating system's list — grey and
            square on Windows, a spinning wheel on a phone — which made the one
            control on this page the only thing not drawn in the site's own
            hand. This is a button and a panel, both built from the same tokens
            as the bar around them: a row of years, then the twelve months.

            Alpine only opens and closes it. Which month is picked stays with
            the server, so the label can never drift out of step with the cards
            below — including when the arrows move it.
        --}}
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

            {{-- `wire:ignore.self` covers the panel's own attributes and
                 nothing else: whether it is open is Alpine's inline style, and
                 a re-render arriving from the arrows must not reach in and
                 reset it. The years and months inside still morph. --}}
            <div class="landing-toolbar-panel"
                 wire:ignore.self
                 x-show="open"
                 x-cloak
                 x-transition.opacity.duration.150ms>

                {{-- The year stays put when picked: the panel is still open on
                     the month that has to be chosen next. --}}
                <div class="landing-toolbar-years">
                    @foreach ($yearOptions as $year)
                        <button type="button"
                                class="landing-toolbar-year {{ $year === $activeYear ? 'is-active' : '' }}"
                                wire:click="selectYear({{ $year }})">
                            {{ $year }}
                        </button>
                    @endforeach
                </div>

                <div class="landing-toolbar-months" role="listbox" aria-label="Pilih bulan">
                    @foreach ($monthOptions as $value => $label)
                        <button type="button"
                                role="option"
                                aria-selected="{{ $value === $this->month ? 'true' : 'false' }}"
                                class="landing-toolbar-pick {{ $value === $this->month ? 'is-active' : '' }}"
                                wire:click="selectMonth('{{ $value }}')"
                                @click="open = false">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Reading another month is a round trip; saying so beats a list
                 that sits still for a moment. The slot is always in the row, so
                 nothing shuffles sideways when it fills. --}}
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
            Daftar menampilkan <strong>{{ $monthLabel }}</strong>,
            Jumat terdekat di atas tetap dihitung dari hari ini.
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                    wire:click="resetMonth">
                Kembali ke bulan ini
            </button>
        </p>
    @endunless

    {{--
        Dimmed rather than emptied while the next month arrives, so the page
        keeps its height and the scroll position does not jump.

        `wire:key` carries the date of the Friday it holds. Livewire morphs
        matching nodes in place, which would rewrite every name on the card with
        no sense of anything having happened; keys that change make it replace
        the cards instead, and a replaced element runs its entrance again.
    --}}
    <div wire:loading.class="is-fetching"
         wire:target="selectMonth, selectYear, previousMonth, nextMonth, resetMonth"
         class="landing-friday-swap">

        @if ($schedules->isEmpty())
            <div class="landing-empty" wire:key="empty-{{ $this->month }}" data-aos>
                <i class="bi bi-calendar-x"></i>
                <p class="mb-0">
                    Belum ada jadwal khutbah yang diumumkan untuk {{ $monthLabel }}.
                </p>
            </div>
        @else
            {{-- A month holds four Fridays, five at most. Side by side they
                 read as one month rather than as a queue, and the row spans the
                 same width as the bar above it — unless there are only one or
                 two, which are held to a card's width rather than stretched to
                 fill a row they cannot fill. --}}
            <div class="landing-friday-grid {{ $schedules->count() < 3 ? 'is-sparse' : '' }}">
                @foreach ($schedules as $index => $friday)
                    <div wire:key="friday-{{ $friday->date->toDateString() }}"
                         data-aos data-aos-delay="{{ min(($index + 1) * 60, 300) }}">
                        @include('partials.portal.friday-card', [
                            'friday' => $friday,
                            'isFeatured' => $friday->date->toDateString() === $featuredDate,
                            'isGrid' => true,
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
