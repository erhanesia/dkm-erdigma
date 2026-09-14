{{--
    One Friday entry.

    Only the roster and the theme are shown. A khatib's name is announced on the
    noticeboard days in advance, so it belongs on a public page — their contact
    details do not, and are never passed here.

    Expects: $friday, $isFeatured (bool), $isGrid (bool, optional)

    `$isGrid` stacks the same card vertically so it can sit beside its siblings
    in a month grid. The list form is the default: in a half-width column on the
    home page there is no room to put four of them side by side.
--}}
<a href="{{ route('portal.friday-schedules.show', ['date' => $friday->date->toDateString()]) }}"
   class="landing-friday {{ ($isGrid ?? false) ? 'is-grid' : '' }} {{ $isFeatured ? 'is-featured' : '' }}">
    <div class="landing-friday-date">
        <span class="landing-friday-day">{{ $friday->date->format('d') }}</span>
        <span class="landing-friday-month">
            {{ \App\Support\Helpers\DateHelper::shortMonthName((int) $friday->date->format('n')) }}
        </span>
    </div>

    <div class="flex-grow-1 min-w-0">
        @if ($isFeatured)
            <span class="landing-friday-flag mb-2">Jumat terdekat</span>
        @endif

        <div class="landing-friday-theme">
            {{ $friday->theme ?: 'Tema belum ditentukan' }}
        </div>

        <div class="landing-friday-people">
            <span>
                <i class="bi bi-person-video3 me-1"></i>
                Khatib: <strong>{{ $friday->khatibName() }}</strong>
            </span>

            @if ($friday->imam)
                <span>
                    <i class="bi bi-person me-1"></i>
                    Imam: <strong>{{ $friday->imam->name }}</strong>
                </span>
            @endif
        </div>
    </div>

    <div class="landing-friday-time">
        <i class="bi bi-clock me-1"></i>
        {{ $friday->start_time ? substr((string) $friday->start_time, 0, 5) : '11:45' }}
        <i class="bi bi-chevron-right ms-2 landing-chevron"></i>
    </div>
</a>
