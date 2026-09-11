@use('App\Support\Helpers\DateHelper')

{{--
    Today's six timings as a row of cards, with the next one lifted out.

    Expects: $timings (from PublicSchedule::timings), $nextPrayer
--}}
<div class="row g-3 g-lg-4">
    @foreach ($timings as $index => $entry)
        @php($isNext = $entry['prayer'] === $nextPrayer)

        <div class="col-6 col-md-4 col-lg-2"
             data-aos
             data-aos-delay="{{ min(($index + 1) * 60, 300) }}">
            <div class="landing-prayer {{ $isNext ? 'is-next' : '' }}">
                @if ($isNext)
                    <span class="landing-prayer-flag">Berikutnya</span>
                @endif

                <i class="bi bi-{{ $entry['prayer']->icon() }} landing-prayer-icon text-{{ $entry['prayer']->color() }}"></i>

                <div class="landing-prayer-name">{{ $entry['prayer']->label() }}</div>
                <div class="landing-prayer-time">{{ $entry['time'] }}</div>
            </div>
        </div>
    @endforeach
</div>
