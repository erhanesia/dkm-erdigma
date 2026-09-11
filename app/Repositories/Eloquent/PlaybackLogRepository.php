<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PlaybackStatus;
use App\Models\PlaybackLog;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<PlaybackLog>
 */
class PlaybackLogRepository extends BaseRepository implements PlaybackLogRepositoryInterface
{
    protected string $defaultOrderColumn = 'scheduled_at';

    protected string $defaultOrderDirection = 'desc';

    protected function model(): string
    {
        return PlaybackLog::class;
    }

    public function paginateFiltered(): LengthAwarePaginator
    {
        return QueryBuilder::for(PlaybackLog::class)
            ->with(['zone', 'device', 'track'])
            ->allowedFilters(
                AllowedFilter::exact('audio_zone_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('prayer'),
                AllowedFilter::callback('date_from', static function (Builder $query, mixed $value): void {
                    $query->where('scheduled_at', '>=', DateHelper::toCarbon($value)->startOfDay());
                }),
                AllowedFilter::callback('date_to', static function (Builder $query, mixed $value): void {
                    $query->where('scheduled_at', '<=', DateHelper::toCarbon($value)->endOfDay());
                }),
                AllowedFilter::callback('problems_only', static function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOL)) {
                        $query->whereIn('status', PlaybackStatus::problematicValues());
                    }
                }), )
            ->allowedSorts('scheduled_at', 'status')
            ->defaultSort('-scheduled_at')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function ensureSlot(int $zoneId, string $referenceKey, array $attributes): PlaybackLog
    {
        return $this->query()->firstOrCreate(
            ['audio_zone_id' => $zoneId, 'reference_key' => $referenceKey],
            $attributes,
        );
    }

    public function findSlot(int $zoneId, string $referenceKey): ?PlaybackLog
    {
        return $this->query()
            ->where('audio_zone_id', $zoneId)
            ->where('reference_key', $referenceKey)
            ->first();
    }

    public function overduePending(int $thresholdSeconds): Collection
    {
        return $this->query()
            ->with('zone')
            ->where('status', PlaybackStatus::Pending)
            ->where('scheduled_at', '<', DateHelper::now()->subSeconds($thresholdSeconds))
            ->get();
    }

    public function unresolvedIssues(int $limit = 20): Collection
    {
        return $this->query()
            ->with(['zone', 'device'])
            ->problematic()
            ->unacknowledged()
            ->orderByDesc('scheduled_at')
            ->limit($limit)
            ->get();
    }

    public function countProblemsOn(CarbonImmutable|string $date): int
    {
        return $this->query()->onDate($date)->problematic()->count();
    }

    public function dailyBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->query()
            ->selectRaw('DATE(scheduled_at) as day, status, COUNT(*) as total')
            ->whereBetween('scheduled_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('day', 'status')
            ->get();

        $breakdown = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $breakdown[$cursor->toDateString()] = [
                'date' => $cursor->toDateString(),
                'played' => 0,
                'silent' => 0,
                'failed' => 0,
                'missed' => 0,
            ];
        }

        foreach ($rows as $row) {
            $day = (string) $row->getAttribute('day');
            $status = $row->getAttribute('status');
            $key = $status instanceof PlaybackStatus ? $status->value : (string) $status;

            if (isset($breakdown[$day][$key])) {
                $breakdown[$day][$key] = (int) $row->getAttribute('total');
            }
        }

        return array_values($breakdown);
    }

    public function reliabilityByZone(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->query()
            ->join('audio_zones', 'audio_zones.id', '=', 'playback_logs.audio_zone_id')
            ->selectRaw('audio_zones.name as zone')
            ->selectRaw('SUM(CASE WHEN playback_logs.status = ? THEN 1 ELSE 0 END) as played', [PlaybackStatus::Played->value])
            ->selectRaw('SUM(CASE WHEN playback_logs.status IN (?, ?, ?) THEN 1 ELSE 0 END) as problems', PlaybackStatus::problematicValues())
            ->whereBetween('playback_logs.scheduled_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('audio_zones.id', 'audio_zones.name')
            ->orderBy('audio_zones.name')
            ->get()
            ->map(static fn ($row): array => [
                'zone' => (string) $row->getAttribute('zone'),
                'played' => (int) $row->getAttribute('played'),
                'problems' => (int) $row->getAttribute('problems'),
            ])
            ->all();
    }

    public function pruneOlderThan(CarbonImmutable $cutoff): int
    {
        return $this->query()->where('scheduled_at', '<', $cutoff)->delete();
    }
}
