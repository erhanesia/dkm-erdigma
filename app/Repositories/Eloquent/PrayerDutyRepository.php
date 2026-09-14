<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use App\Repositories\Contracts\PrayerDutyRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<PrayerDuty>
 */
class PrayerDutyRepository extends BaseRepository implements PrayerDutyRepositoryInterface
{
    protected string $defaultOrderColumn = 'date';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return PrayerDuty::class;
    }

    public function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->query()
            ->with(['muadzin', 'imam'])
            ->rostered()
            ->between($from, $to)
            ->orderBy('date')
            ->get();
    }

    public function forDate(CarbonImmutable|string $date): Collection
    {
        return $this->query()
            ->with(['muadzin', 'imam'])
            ->rostered()
            ->whereDate('date', DateHelper::toCarbon($date)->toDateString())
            ->get();
    }

    public function findFor(CarbonImmutable|string $date, PrayerName $prayer): ?PrayerDuty
    {
        return $this->query()
            ->whereDate('date', DateHelper::toCarbon($date)->toDateString())
            ->where('prayer', $prayer->value)
            ->first();
    }

    public function saveGrid(array $grid, ?int $createdBy = null): void
    {
        foreach ($grid as $date => $prayers) {
            foreach ($prayers as $prayer => $attributes) {
                if (PrayerName::tryFrom((string) $prayer) === null) {
                    continue;
                }

                $muadzinId = $attributes['muadzin_id'] ?? null;
                $imamId = $attributes['imam_id'] ?? null;

                if ($muadzinId === null && $imamId === null) {
                    $this->query()
                        ->whereDate('date', $date)
                        ->where('prayer', $prayer)
                        ->delete();

                    continue;
                }

                $this->query()->updateOrCreate(
                    ['date' => $date, 'prayer' => $prayer],
                    [...$attributes, 'created_by' => $createdBy],
                );
            }
        }
    }

    public function upcomingForUser(int $userId, int $limit = 5): Collection
    {
        return $this->query()
            ->rostered()
            ->whereDate('date', '>=', DateHelper::today()->toDateString())
            ->where(function (Builder $query) use ($userId): void {
                $query->where('muadzin_id', $userId)->orWhere('imam_id', $userId);
            })
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }
}
