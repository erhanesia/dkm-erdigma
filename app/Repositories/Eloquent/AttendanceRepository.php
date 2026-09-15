<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\AfterHoursSession;
use App\Models\Attendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\NumberHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Attendance>
 */
class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    protected function model(): string
    {
        return Attendance::class;
    }

    public function forSession(int $sessionId): Collection
    {
        return $this->query()
            ->with(['user', 'recorder'])
            ->where('after_hours_session_id', $sessionId)
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->orderBy('users.name')
            ->select('attendances.*')
            ->get();
    }

    public function findFor(int $sessionId, int $userId): ?Attendance
    {
        return $this->query()
            ->where('after_hours_session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    public function seedForSession(AfterHoursSession $session): void
    {
        $memberIds = $session->group->memberships()->where('is_active', true)->pluck('user_id');

        foreach ($memberIds as $userId) {
            $this->query()->firstOrCreate(
                ['after_hours_session_id' => $session->id, 'user_id' => $userId],
                ['status' => AttendanceStatus::Absent->value, 'method' => AttendanceMethod::Manual->value],
            );
        }
    }

    public function saveBulk(AfterHoursSession $session, array $entries, int $recordedBy): void
    {
        foreach ($entries as $entry) {
            $this->query()->updateOrCreate(
                ['after_hours_session_id' => $session->id, 'user_id' => $entry['user_id']],
                [
                    'status' => $entry['status'],
                    'note' => $entry['note'] ?? null,
                    'method' => AttendanceMethod::Manual->value,
                    'recorded_by' => $recordedBy,
                    'checked_in_at' => in_array($entry['status'], AttendanceStatus::attendingValues(), true)
                        ? DateHelper::now()
                        : null,
                ],
            );
        }
    }

    public function recapByMember(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array
    {
        return $this->query()
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->join('after_hours_sessions', 'after_hours_sessions.id', '=', 'attendances.after_hours_session_id')
            ->selectRaw('users.id as user_id, users.name, users.department_name')
            ->selectRaw('COUNT(attendances.id) as total')
            ->selectRaw('SUM(CASE WHEN attendances.status IN (?, ?) THEN 1 ELSE 0 END) as attended', AttendanceStatus::attendingValues())
            ->whereBetween('after_hours_sessions.starts_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('after_hours_sessions.mentoring_group_id', $groupIds))
            ->groupBy('users.id', 'users.name', 'users.department_name')
            ->orderBy('users.name')
            ->get()
            ->map(static function ($row): array {
                $total = (int) $row->getAttribute('total');
                $attended = (int) $row->getAttribute('attended');

                return [
                    'user_id' => (int) $row->getAttribute('user_id'),
                    'name' => (string) $row->getAttribute('name'),
                    'department' => $row->getAttribute('department_name'),
                    'total' => $total,
                    'attended' => $attended,
                    'rate' => NumberHelper::percentageValue($attended, $total),
                ];
            })
            ->all();
    }

    public function recapByGroup(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array
    {
        return $this->query()
            ->join('after_hours_sessions', 'after_hours_sessions.id', '=', 'attendances.after_hours_session_id')
            ->join('mentoring_groups', 'mentoring_groups.id', '=', 'after_hours_sessions.mentoring_group_id')
            ->join('users as mentors', 'mentors.id', '=', 'mentoring_groups.mentor_id')
            ->selectRaw('mentoring_groups.name as group_name, mentors.name as mentor_name')
            ->selectRaw('COUNT(DISTINCT after_hours_sessions.id) as sessions')
            ->selectRaw('COUNT(attendances.id) as expected')
            ->selectRaw('SUM(CASE WHEN attendances.status IN (?, ?) THEN 1 ELSE 0 END) as attended', AttendanceStatus::attendingValues())
            ->whereBetween('after_hours_sessions.starts_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('mentoring_groups.id', $groupIds))
            ->groupBy('mentoring_groups.id', 'mentoring_groups.name', 'mentors.name')
            ->orderBy('mentoring_groups.name')
            ->get()
            ->map(static function ($row): array {
                $expected = (int) $row->getAttribute('expected');
                $attended = (int) $row->getAttribute('attended');

                return [
                    'group' => (string) $row->getAttribute('group_name'),
                    'mentor' => (string) $row->getAttribute('mentor_name'),
                    'sessions' => (int) $row->getAttribute('sessions'),
                    'expected' => $expected,
                    'attended' => $attended,
                    'rate' => NumberHelper::percentageValue($attended, $expected),
                ];
            })
            ->all();
    }

    public function monthlyTrend(int $months = 6, ?array $groupIds = null): array
    {
        $start = DateHelper::today()->subMonths($months - 1)->startOfMonth();

        $rows = $this->query()
            ->join('after_hours_sessions', 'after_hours_sessions.id', '=', 'attendances.after_hours_session_id')
            ->selectRaw("DATE_FORMAT(after_hours_sessions.starts_at, '%Y-%m') as period")
            ->selectRaw('COUNT(attendances.id) as total')
            ->selectRaw('SUM(CASE WHEN attendances.status IN (?, ?) THEN 1 ELSE 0 END) as attended', AttendanceStatus::attendingValues())
            ->where('after_hours_sessions.starts_at', '>=', $start)
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('after_hours_sessions.mentoring_group_id', $groupIds))
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $trend = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo(DateHelper::today()); $cursor = $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);
            $total = $row === null ? 0 : (int) $row->getAttribute('total');
            $attended = $row === null ? 0 : (int) $row->getAttribute('attended');

            $trend[] = [
                'month' => DateHelper::formatMonthYear($cursor),
                'attended' => $attended,
                'total' => $total,
                'rate' => NumberHelper::percentageValue($attended, $total),
            ];
        }

        return $trend;
    }

    public function attendanceRateInMonth(CarbonImmutable $month, ?array $groupIds = null): float
    {
        $row = $this->query()
            ->join('after_hours_sessions', 'after_hours_sessions.id', '=', 'attendances.after_hours_session_id')
            ->selectRaw('COUNT(attendances.id) as total')
            ->selectRaw('SUM(CASE WHEN attendances.status IN (?, ?) THEN 1 ELSE 0 END) as attended', AttendanceStatus::attendingValues())
            ->whereBetween('after_hours_sessions.starts_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('after_hours_sessions.mentoring_group_id', $groupIds))
            ->first();

        return NumberHelper::percentageValue(
            (int) ($row?->getAttribute('attended') ?? 0),
            (int) ($row?->getAttribute('total') ?? 0),
        );
    }

    public function statusCountsByMonth(int $year, ?int $userId = null, ?array $groupIds = null): array
    {
        $counts = array_fill(1, 12, array_fill_keys(AttendanceStatus::values(), 0));

        $rows = $this->statisticsQuery($userId, $groupIds)
            ->whereBetween('after_hours_sessions.starts_at', [sprintf('%d-01-01 00:00:00', $year), sprintf('%d-12-31 23:59:59', $year)])
            ->selectRaw('MONTH(after_hours_sessions.starts_at) as month_number, attendances.status, COUNT(attendances.id) as total')
            ->groupBy('month_number', 'attendances.status')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $month = (int) $row->month_number;

            if (isset($counts[$month][$row->status])) {
                $counts[$month][$row->status] = (int) $row->total;
            }
        }

        return $counts;
    }

    public function firstRecordedYear(?int $userId = null, ?array $groupIds = null): ?int
    {
        $first = $this->statisticsQuery($userId, $groupIds)->toBase()->min('after_hours_sessions.starts_at');

        return $first === null ? null : (int) substr((string) $first, 0, 4);
    }

    /**
     * Attendance that has actually happened.
     *
     * Every session seeds an `absent` row per member the moment it is
     * scheduled, so an upcoming session would count as a room full of
     * absences. A cancelled one never took place at all.
     *
     * @param  array<int, int>|null  $groupIds
     * @return Builder<Attendance>
     */
    private function statisticsQuery(?int $userId, ?array $groupIds): Builder
    {
        return $this->query()
            ->join('after_hours_sessions', 'after_hours_sessions.id', '=', 'attendances.after_hours_session_id')
            ->whereNull('after_hours_sessions.deleted_at')
            ->where('after_hours_sessions.status', '!=', SessionStatus::Cancelled->value)
            ->where('after_hours_sessions.starts_at', '<=', DateHelper::now())
            ->when($userId !== null, static fn (Builder $query) => $query->where('attendances.user_id', $userId))
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('after_hours_sessions.mentoring_group_id', $groupIds));
    }
}
