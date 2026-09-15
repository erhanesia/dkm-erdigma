<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\AfterHours\AfterHoursSessionService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The public After Hours page's calendar: one month of announced sessions as a
 * grid, and the same month as an agenda underneath.
 *
 * Built like the prayer and Friday month browsers, so the three public schedule
 * pages behave as one: the same toolbar, the same `bulan` in the address bar,
 * and only the month redrawn when the visitor moves — never the hero above it.
 */
class PublicSessionCalendar extends Component
{
    /**
     * How far ahead the calendar opens.
     *
     * A recurring series runs for six months at most, so nothing can be
     * scheduled further out than this; a month beyond it would only ever be
     * empty.
     */
    private const MONTHS_AHEAD = 6;

    /**
     * `Y-m` — the single source of truth for what is on screen.
     *
     * The same `bulan` the prayer and Friday pages use, in the same format, so
     * moving between the three keeps the month the visitor was reading. The
     * navigation links carry it across with `data-carry-month`.
     */
    #[Url(as: 'bulan', keep: true)]
    public string $month = '';

    /**
     * `Y-m` of the earliest session ever announced publicly — how far back the
     * calendar opens.
     *
     * Taken from the data rather than a fixed number of months: a visitor may
     * look back as far as there is anything to see, and is never offered the
     * empty months before the first session. Kept as a month string rather than
     * a date so it compares in the same timezone as every other month here.
     */
    #[Locked]
    public string $firstMonth = '';

    /**
     * Livewire assigns a matching query parameter onto `$month` before this
     * runs, so it arrives as a string — empty, or whatever was typed into the
     * address bar.
     */
    public function mount(AfterHoursSessionService $sessions): void
    {
        $this->firstMonth = $sessions->firstPublicMonth() ?? '';

        $this->showMonth(DateHelper::startOfMonthFrom($this->month));
    }

    public function previousMonth(): void
    {
        $this->showMonth($this->monthDate()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->showMonth($this->monthDate()->addMonth());
    }

    /**
     * Back to the month the visitor is actually in.
     */
    public function resetMonth(): void
    {
        $this->showMonth(DateHelper::today()->startOfMonth());
    }

    /**
     * A month from the panel's grid, as a whole `Y-m`.
     */
    public function selectMonth(string $month): void
    {
        $this->showMonth(DateHelper::startOfMonthFrom($month));
    }

    /**
     * A year from the panel's top row: the same month in that year, or the
     * nearest month that year can open on — picking the first year from
     * September lands on the first month with sessions, not back on today.
     */
    public function selectYear(int $year): void
    {
        $target = $this->monthDate()->setYear($year);

        if ($target->lessThan($this->earliestMonth())) {
            $target = $this->earliestMonth();
        } elseif ($target->greaterThan($this->latestMonth())) {
            $target = $this->latestMonth();
        }

        $this->showMonth($target);
    }

    public function render(AfterHoursSessionService $sessions): View
    {
        $month = $this->monthDate();
        $monthSessions = $sessions->publicInMonth($month);

        return view('livewire.public-session-calendar', [
            'monthDate' => $month,
            'weeks' => $this->weeks($month),
            'weekdayLabels' => array_map(
                static fn (string $day): string => mb_substr($day, 0, 3),
                array_values(DateHelper::weekdayOptions()),
            ),
            'sessionsByDate' => $monthSessions->groupBy(
                static fn ($session): string => $session->starts_at->toDateString(),
            ),
            'total' => $monthSessions->count(),
            'todayDate' => DateHelper::today()->toDateString(),

            'monthLabel' => DateHelper::formatMonthYear($month),
            'monthOptions' => $this->monthOptions($month),
            'yearOptions' => range($this->earliestMonth()->year, $this->latestMonth()->year),
            'activeYear' => $month->year,
            'isCurrentMonth' => $month->equalTo(DateHelper::today()->startOfMonth()),
            'hasPreviousMonth' => $this->isBrowsable($month->subMonth()),
            'hasNextMonth' => $this->isBrowsable($month->addMonth()),
        ]);
    }

    private function monthDate(): CarbonImmutable
    {
        return DateHelper::startOfMonthFrom($this->month);
    }

    /**
     * Moves to a month, or back to the current one when it falls outside the
     * browsable window — this value can arrive from the address bar.
     */
    private function showMonth(CarbonImmutable $month): void
    {
        $target = $this->isBrowsable($month) ? $month : DateHelper::today()->startOfMonth();

        $this->month = $target->format('Y-m');
    }

    /**
     * Within the window — which is also what the view uses to disable the
     * arrows and the months in the panel that cannot be opened.
     */
    private function isBrowsable(CarbonImmutable $month): bool
    {
        return $month->greaterThanOrEqualTo($this->earliestMonth())
            && $month->lessThanOrEqualTo($this->latestMonth());
    }

    /**
     * The first month with a public session, and never later than this month:
     * the calendar always opens on today, even before anything is announced.
     */
    private function earliestMonth(): CarbonImmutable
    {
        $current = DateHelper::today()->startOfMonth();

        if ($this->firstMonth === '') {
            return $current;
        }

        $first = DateHelper::startOfMonthFrom($this->firstMonth);

        return $first->lessThan($current) ? $first : $current;
    }

    private function latestMonth(): CarbonImmutable
    {
        return DateHelper::today()->startOfMonth()->addMonthsNoOverflow(self::MONTHS_AHEAD);
    }

    /**
     * The grid's weeks, Monday to Sunday, padded with the days of the months
     * either side so every row is a whole week.
     *
     * @return array<int, array<int, CarbonImmutable>>
     */
    private function weeks(CarbonImmutable $month): array
    {
        $first = $month->startOfWeek(CarbonInterface::MONDAY);
        $last = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $weeks = [];
        $index = 0;

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $weeks[intdiv($index, 7)][] = $day;
            $index++;
        }

        return $weeks;
    }

    /**
     * The twelve months of the year on screen, each marked with whether the
     * calendar can open on it.
     *
     * Values carry the year so a month picked from the grid lands in the year
     * the panel was showing.
     *
     * @return array<string, array{label: string, browsable: bool}>
     */
    private function monthOptions(CarbonImmutable $month): array
    {
        $options = [];

        foreach (range(1, 12) as $number) {
            $value = sprintf('%04d-%02d', $month->year, $number);

            $options[$value] = [
                'label' => DateHelper::monthName($number),
                'browsable' => $this->isBrowsable(DateHelper::startOfMonthFrom($value)),
            ];
        }

        return $options;
    }
}
