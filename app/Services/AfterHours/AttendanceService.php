<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AfterHoursSession;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\NumberHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Attendance for after-hours sessions — both the mentor's checklist and the
 * employee's own QR check-in.
 */
class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendances,
    ) {}

    /**
     * @return Collection<int, Attendance>
     */
    public function forSession(AfterHoursSession $session): Collection
    {
        $this->attendances->seedForSession($session->loadMissing('group'));

        return $this->attendances->forSession($session->id);
    }

    /**
     * Mentor submits the whole roll call at once.
     *
     * @param  array<int, array{user_id: int, status: string, note: string|null}>  $entries
     */
    public function recordBulk(AfterHoursSession $session, array $entries, User $recorder): void
    {
        if (! $session->status->acceptsAttendance()) {
            throw new BusinessRuleException('Presensi hanya bisa diisi untuk sesi yang terjadwal atau sedang berlangsung.');
        }

        $this->attendances->saveBulk($session, $entries, $recorder->id);
    }

    /**
     * Employee scans the QR code and checks themselves in.
     */
    public function checkIn(AfterHoursSession $session, User $user): Attendance
    {
        if (! $session->isCheckInOpen()) {
            throw new BusinessRuleException(
                'Presensi untuk sesi ini sedang tidak dibuka. Silakan hubungi mentor Anda.',
            );
        }

        if (! $this->isExpectedAt($session, $user)) {
            throw new BusinessRuleException('Anda tidak terdaftar sebagai anggota halaqah pada sesi ini.');
        }

        $existing = $this->attendances->findFor($session->id, $user->id);

        if ($existing !== null && $existing->status->countsAsAttending()) {
            throw new BusinessRuleException('Anda sudah melakukan presensi untuk sesi ini.');
        }

        $status = $session->isLateAt() ? AttendanceStatus::Late : AttendanceStatus::Present;

        /** @var Attendance $attendance */
        $attendance = $this->attendances->updateOrCreate(
            ['after_hours_session_id' => $session->id, 'user_id' => $user->id],
            [
                'status' => $status->value,
                'method' => AttendanceMethod::QrCode->value,
                'checked_in_at' => DateHelper::now(),
                'recorded_by' => $user->id,
            ],
        );

        return $attendance;
    }

    /**
     * Single-row edit from the mentor's table.
     */
    public function updateEntry(AfterHoursSession $session, int $userId, AttendanceStatus $status, ?string $note, User $recorder): Attendance
    {
        /** @var Attendance $attendance */
        $attendance = $this->attendances->updateOrCreate(
            ['after_hours_session_id' => $session->id, 'user_id' => $userId],
            [
                'status' => $status->value,
                'note' => $note,
                'method' => AttendanceMethod::Manual->value,
                'recorded_by' => $recorder->id,
                'checked_in_at' => $status->countsAsAttending() ? DateHelper::now() : null,
            ],
        );

        return $attendance;
    }

    /**
     * @return array<int, array{user_id: int, name: string, department: string|null, total: int, attended: int, rate: float}>
     */
    public function recapByMember(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array
    {
        return $this->attendances->recapByMember($from, $to, $groupIds);
    }

    /**
     * @return array<int, array{group: string, mentor: string, sessions: int, expected: int, attended: int, rate: float}>
     */
    public function recapByGroup(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): array
    {
        return $this->attendances->recapByGroup($from, $to, $groupIds);
    }

    /**
     * @return array<int, array{month: string, attended: int, total: int, rate: float}>
     */
    public function monthlyTrend(int $months = 6, ?array $groupIds = null): array
    {
        return $this->attendances->monthlyTrend($months, $groupIds);
    }

    /**
     * Per-session tally shown above the roll call table.
     *
     * @return array<string, int>
     */
    public function tally(AfterHoursSession $session): array
    {
        $tally = array_fill_keys(AttendanceStatus::values(), 0);

        foreach ($this->attendances->forSession($session->id) as $attendance) {
            $tally[$attendance->status->value]++;
        }

        return $tally;
    }

    /**
     * One year of attendance, month by month, for the statistics charts.
     *
     * Only sessions that have started and were not cancelled count — see
     * `AttendanceRepository::statisticsQuery()`.
     *
     * @param  array<int, int>|null  $groupIds
     * @return array{months: array<int, array<string, int>>, totals: array<string, int>, recorded: int, attended: int, rate: float}
     */
    public function statistics(int $year, ?int $userId = null, ?array $groupIds = null): array
    {
        $months = $this->attendances->statusCountsByMonth($year, $userId, $groupIds);
        $totals = array_fill_keys(AttendanceStatus::values(), 0);

        foreach ($months as $counts) {
            foreach ($counts as $status => $count) {
                $totals[$status] += $count;
            }
        }

        $recorded = array_sum($totals);
        $attended = array_sum(array_intersect_key($totals, array_flip(AttendanceStatus::attendingValues())));

        return [
            'months' => $months,
            'totals' => $totals,
            'recorded' => $recorded,
            'attended' => $attended,
            'rate' => NumberHelper::percentageValue($attended, $recorded),
        ];
    }

    /**
     * @param  array<int, int>|null  $groupIds
     */
    public function firstRecordedYear(?int $userId = null, ?array $groupIds = null): ?int
    {
        return $this->attendances->firstRecordedYear($userId, $groupIds);
    }

    /**
     * The years a statistics view can open on, newest first: from the first
     * year with attendance up to this one.
     *
     * @return array<int, int>
     */
    public function yearOptions(?int $firstYear): array
    {
        $thisYear = DateHelper::today()->year;

        return range($thisYear, min($firstYear ?? $thisYear, $thisYear));
    }

    /**
     * The year asked for in `?tahun=`, or this year when it is not one of the
     * options — a typed-in 1999 opens on the present rather than on nothing.
     *
     * @param  array<int, int>  $options
     */
    public function resolveYear(int $requested, array $options): int
    {
        return in_array($requested, $options, true) ? $requested : DateHelper::today()->year;
    }

    private function isExpectedAt(AfterHoursSession $session, User $user): bool
    {
        return $session->group
            ->memberships()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
