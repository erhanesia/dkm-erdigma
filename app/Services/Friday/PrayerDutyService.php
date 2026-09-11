<?php

declare(strict_types=1);

namespace App\Services\Friday;

use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use App\Models\User;
use App\Repositories\Contracts\PrayerDutyRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The daily muezzin/imam roster, edited as a week-at-a-time grid.
 */
class PrayerDutyService
{
    public function __construct(
        private readonly PrayerDutyRepositoryInterface $duties,
    ) {}

    /**
     * Week grid shaped for the Blade table: date => prayer => duty|null.
     *
     * @return array<string, array<string, PrayerDuty|null>>
     */
    public function weekGrid(CarbonImmutable $weekStart): array
    {
        $weekEnd = $weekStart->addDays(6);
        $duties = $this->duties->between($weekStart, $weekEnd);

        $grid = [];

        for ($cursor = $weekStart; $cursor->lessThanOrEqualTo($weekEnd); $cursor = $cursor->addDay()) {
            $day = $cursor->toDateString();

            foreach (PrayerName::withAdhan() as $prayer) {
                $grid[$day][$prayer->value] = $duties->first(
                    static fn (PrayerDuty $duty): bool => DateHelper::toCarbon($duty->date)->toDateString() === $day
                        && $duty->prayer === $prayer,
                );
            }
        }

        return $grid;
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
