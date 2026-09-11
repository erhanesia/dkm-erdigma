<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AfterHoursSession;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Attendance>
 */
interface AttendanceRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, Attendance>
     */
    public function forSession(int $sessionId): Collection;

    public function findFor(int $sessionId, int $userId): ?Attendance;

    /**
     * Create an `absent` row for every group member so the mentor starts from a
     * complete checklist rather than an empty page.
     */
    public function seedForSession(AfterHoursSession $session): void;

    /**
     * @param  array<int, array{user_id: int, status: string, note: string|null}>  $entries
     */
    public function saveBulk(AfterHoursSession $session, array $entries, int $recordedBy): void;

    /**
     * Per-member attendance rate over a period.
     *
     * @return array<int, array{user_id: int, name: string, department: string|null, total: int, attended: int, rate: float}>
     */
    public function recapByMember(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array;

    /**
     * Per-group attendance rate over a period.
     *
     * @return array<int, array{group: string, mentor: string, sessions: int, expected: int, attended: int, rate: float}>
     */
    public function recapByGroup(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array;

    /**
     * Monthly attendance trend for the dashboard chart.
     *
     * @return array<int, array{month: string, attended: int, total: int, rate: float}>
     */
    public function monthlyTrend(int $months = 6, ?array $groupIds = null): array;

    public function attendanceRateInMonth(CarbonImmutable $month, ?array $groupIds = null): float;
}
