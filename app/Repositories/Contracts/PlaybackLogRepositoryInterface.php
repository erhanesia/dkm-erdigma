<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PlaybackLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<PlaybackLog>
 */
interface PlaybackLogRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, PlaybackLog>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * Idempotent slot creation — the planner may run many times a day, and a
     * playback must never be duplicated for the same zone + reference key.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function ensureSlot(int $zoneId, string $referenceKey, array $attributes): PlaybackLog;

    public function findSlot(int $zoneId, string $referenceKey): ?PlaybackLog;

    /**
     * Pending slots whose scheduled time has passed by more than the threshold.
     *
     * @return Collection<int, PlaybackLog>
     */
    public function overduePending(int $thresholdSeconds): Collection;

    /**
     * @return Collection<int, PlaybackLog>
     */
    public function unresolvedIssues(int $limit = 20): Collection;

    public function countProblemsOn(CarbonImmutable|string $date): int;

    /**
     * Daily success/failure counts for the monitoring chart.
     *
     * @return array<int, array{date: string, played: int, silent: int, failed: int, missed: int}>
     */
    public function dailyBreakdown(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Status counts grouped by zone for the given window.
     *
     * @return array<int, array{zone: string, played: int, problems: int}>
     */
    public function reliabilityByZone(CarbonImmutable $from, CarbonImmutable $to): array;

    public function pruneOlderThan(CarbonImmutable $cutoff): int;
}
