<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ScheduleSource;
use App\Models\PrayerSchedule;
use App\Repositories\Contracts\PrayerScheduleRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<PrayerSchedule>
 */
class PrayerScheduleRepository extends BaseRepository implements PrayerScheduleRepositoryInterface
{
    protected string $defaultOrderColumn = 'date';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return PrayerSchedule::class;
    }

    public function forDate(CarbonImmutable|string $date): ?PrayerSchedule
    {
        return $this->query()->forDate($date)->first();
    }

    public function between(CarbonImmutable|string $from, CarbonImmutable|string $to): Collection
    {
        return $this->query()->between($from, $to)->orderBy('date')->get();
    }

    public function missingDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $existing = $this->query()
            ->between($from, $to)
            ->pluck('date')
            ->map(static fn ($date): string => DateHelper::toCarbon($date)->toDateString())
            ->all();

        $missing = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $day = $cursor->toDateString();

            if (! in_array($day, $existing, true)) {
                $missing[] = $day;
            }
        }

        return $missing;
    }

    public function storeTimings(
        CarbonImmutable $date,
        array $timings,
        string $method,
        bool $force = false,
        ScheduleSource $source = ScheduleSource::Calculated,
    ): PrayerSchedule {
        $existing = $this->forDate($date);

        if ($existing !== null && $existing->is_manual_override && ! $force) {
            return $existing;
        }

        return $this->query()->updateOrCreate(
            ['date' => $date->toDateString()],
            [...$timings, 'calculation_method' => $method, 'source' => $source->value],
        );
    }

    public function latestDate(): ?CarbonImmutable
    {
        $latest = $this->query()->max('date');

        return $latest === null ? null : DateHelper::toCarbon($latest);
    }
}
