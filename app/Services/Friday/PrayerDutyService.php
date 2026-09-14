<?php

declare(strict_types=1);

namespace App\Services\Friday;

use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use App\Models\User;
use App\Repositories\Contracts\PrayerDutyRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Dzuhur and Ashar roster for the working week — imam and muadzin —
 * edited a week at a time and printed for any range.
 */
class PrayerDutyService
{
    public function __construct(
        private readonly PrayerDutyRepositoryInterface $duties,
    ) {}

    /**
     * The working week shaped for the Blade table: date => prayer => duty|null.
     *
     * @return array<string, array<string, PrayerDuty|null>>
     */
    public function weekGrid(CarbonImmutable $weekStart): array
    {
        return $this->gridBetween($weekStart, $weekStart->addDays(4));
    }

    /**
     * The same shape for any window — the editor asks for a week, the printed
     * roster for whatever range the board picks.
     *
     * Every rostered day in the window gets a row and every rostered prayer a
     * column, filled or not, so a gap in the roster shows up as a gap on the
     * sheet.
     *
     * @return array<string, array<string, PrayerDuty|null>>
     */
    public function gridBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $duties = $this->duties->between($from, $to)->keyBy(
            static fn (PrayerDuty $duty): string => DateHelper::toCarbon($duty->date)->toDateString().'|'.$duty->prayer->value,
        );

        $grid = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            if (! $this->isRosteredDay($cursor)) {
                continue;
            }

            $day = $cursor->toDateString();

            foreach (PrayerName::rostered() as $prayer) {
                $grid[$day][$prayer->value] = $duties->get($day.'|'.$prayer->value);
            }
        }

        return $grid;
    }

    /**
     * Whether officers are assigned on this day: Monday to Friday.
     *
     * These are the office's congregations, and nobody is in on the weekend —
     * Saturday is work-from-anywhere and Sunday a day off — so there is no one
     * on site to lead either prayer or to call it.
     */
    public function isRosteredDay(CarbonInterface $date): bool
    {
        return $date->isWeekday();
    }

    /**
     * Persist the whole week in one submit.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $grid
     */
    public function saveWeek(array $grid, User $actor): void
    {
        $this->duties->saveGrid($grid, $actor->id);
    }

    /**
     * @return Collection<int, PrayerDuty>
     */
    public function forDate(CarbonImmutable|string $date): Collection
    {
        return $this->duties->forDate($date);
    }

    /**
     * @return Collection<int, PrayerDuty>
     */
    public function upcomingForUser(User $user, int $limit = 5): Collection
    {
        return $this->duties->upcomingForUser($user->id, $limit);
    }

    /**
     * Muezzin on duty for a given prayer today, shown next to the countdown.
     */
    public function muadzinFor(PrayerName $prayer, ?CarbonImmutable $date = null): ?User
    {
        return $this->duties->findFor($date ?? DateHelper::today(), $prayer)?->muadzin;
    }
}
