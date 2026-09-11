<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Friday\FridayScheduleService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One month of the Friday roster on the public page, with its own month picker.
 *
 * Browsing used to be a GET form and two links, so looking at next month
 * reloaded the entire page — the hero, the countdown, the footer — to redraw
 * four cards. Only the cards differ between one month and the next, so only the
 * cards are replaced now.
 *
 * The hero deliberately stays outside this component: it announces the *next*
 * Friday at this mosque, and following the visitor's browsing into March would
 * make it quietly wrong.
 */
class PublicFridayMonth extends Component
{
    /**
     * How far either side of this year the visitor may browse.
     *
     * A roster is filled a few weeks ahead at most, so a year back and two
     * ahead already covers every month that will ever have rows in it. Whole
     * years rather than whole months, so every month in the picker's grid is
     * reachable whichever year it is showing.
     */
    private const YEARS_BACK = 1;

    private const YEARS_AHEAD = 2;

    /**
     * `Y-m` — the single source of truth for what is on screen.
     *
     * Kept as a query parameter so a month is still a link someone can paste
     * into a chat thread, and still survives a refresh. It is the same `bulan`
     * the prayer page uses, in the same format, so stepping between the two
     * keeps the month the visitor was reading.
     */
    #[Url(as: 'bulan', keep: true)]
    public string $month = '';

    /**
     * Livewire assigns a matching query parameter onto the typed property
     * before this runs, so `$month` arrives as a string — empty, not null, and
     * possibly whatever someone typed into the address bar.
     */
    public function mount(): void
    {
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
     * A year from the panel's top row — the same month, another year.
     */
    public function selectYear(int $year): void
    {
        $this->showMonth($this->monthDate()->setYear($year));
    }

    public function render(FridayScheduleService $fridaySchedules): View
    {
        $month = $this->monthDate();
        $schedules = $fridaySchedules->forMonth($month);

        return view('livewire.public-friday-month', [
            'monthDate' => $month,
            'schedules' => $schedules,

            /*
             * The badge belongs to the one Friday the hero is counting down to,
             * not to whichever card happens to come first in the month being
             * read — so browsing ahead marks nothing until the visitor arrives
             * at the month that actually holds it.
             */
            'featuredDate' => $fridaySchedules->nextUpcoming()?->date->toDateString(),

            'monthLabel' => DateHelper::formatMonthYear($month),
            'monthOptions' => $this->monthOptions($month),
            'yearOptions' => $this->yearOptions(),
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
     * Within the browsable window — which is also what the view uses to disable
     * its previous/next buttons.
     */
    private function isBrowsable(CarbonImmutable $month): bool
    {
        $thisYear = DateHelper::today()->year;

        return $month->year >= $thisYear - self::YEARS_BACK
            && $month->year <= $thisYear + self::YEARS_AHEAD;
    }

    /**
     * The twelve months of the year on screen, as `Y-m => Januari`.
     *
     * Values carry the year so a month picked from the grid lands in the year
     * the panel was showing.
     *
     * @return array<string, string>
     */
    private function monthOptions(CarbonImmutable $month): array
    {
        $options = [];

        foreach (range(1, 12) as $number) {
            $options[sprintf('%04d-%02d', $month->year, $number)] = DateHelper::monthName($number);
        }

        return $options;
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $thisYear = DateHelper::today()->year;

        return range($thisYear - self::YEARS_BACK, $thisYear + self::YEARS_AHEAD);
    }
}
