<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AfterHoursSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<AfterHoursSession>
 */
interface AfterHoursSessionRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<int, int>  $groupIds  Restricts a mentor to their own halaqah.
     * @return LengthAwarePaginator<int, AfterHoursSession>
     */
    public function paginateFiltered(?array $groupIds = null): LengthAwarePaginator;

    public function findByQrToken(string $token): ?AfterHoursSession;

    /**
     * @param  array<int, int>|null  $groupIds
     * @return Collection<int, AfterHoursSession>
     */
    public function upcoming(int $limit = 5, ?array $groupIds = null): Collection;

    /**
     * Sessions an employee is expected at, through their halaqah membership.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function upcomingForMember(int $userId, int $limit = 5): Collection;

    /**
     * Past sessions for this member, carrying only their own attendance.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function pastForMember(int $userId, int $limit = 20): Collection;

    /**
     * The halaqah's most recent session with a last reading written down.
     * Cancelled sessions are skipped: no reading happened at them.
     */
    public function latestReadingForGroup(int $groupId): ?AfterHoursSession;

    /**
     * Sessions the mosque has agreed to announce publicly.
     *
     * @return Collection<int, AfterHoursSession>
     */
    /**
     * One session the mosque has agreed to announce, or null.
     */
    public function findPublic(int $id): ?AfterHoursSession;

    public function upcomingPublic(int $limit = 5): Collection;

    /**
     * @return Collection<int, AfterHoursSession>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): Collection;

    /**
     * Sessions whose window has passed but which are still marked open.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function endedButOpen(): Collection;

    public function countInMonth(CarbonImmutable $month): int;

    /**
     * Calendar dates in the window on which this halaqah already meets.
     * Cancelled sessions leave their date free.
     *
     * @return array<int, string> `Y-m-d`
     */
    public function datesTakenByGroup(int $groupId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Later sessions in the same series that can still move: scheduled, not
     * yet started, and with nobody marked present.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function movableFromInSeries(AfterHoursSession $session): Collection;
}
