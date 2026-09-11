<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\User;
use App\Repositories\Contracts\AudioZoneRepositoryInterface;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Services\AfterHours\AttendanceService;
use App\Services\Device\DeviceService;
use App\Services\Device\PlaybackMonitorService;
use App\Services\Friday\FridayScheduleService;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;

/**
 * Assembles the landing dashboard.
 *
 * Every card here maps back to a complaint in the brief, so a glance answers
 * "is anything broken right now?" — which is precisely what the old local script
 * could never tell anyone.
 */
class DashboardService
{
    public function __construct(
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly DeviceService $devices,
        private readonly PlaybackMonitorService $playbackMonitor,
        private readonly PlaybackLogRepositoryInterface $playbackLogs,
        private readonly FridayScheduleService $fridaySchedules,
        private readonly AfterHoursSessionService $sessions,
        private readonly AttendanceService $attendances,
        private readonly AudioZoneRepositoryInterface $zones,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAdministrator(): array
    {
        $today = DateHelper::today();
        $monthStart = $today->startOfMonth();

        return [
            'schedule' => $this->prayerSchedules->today(),
            'next_prayer' => $this->prayerSchedules->nextPrayer(),
            'hijri_date' => DateHelper::formatHijri($today),
            'device_health' => $this->devices->healthSummary(),
            'offline_devices' => $this->devices->offlineDevices(),
            'playback_summary' => $this->playbackMonitor->summary($today->subDays(29), $today),
            'playback_today_problems' => $this->playbackLogs->countProblemsOn($today),
            'playback_issues' => $this->playbackMonitor->unresolvedIssues(6),
            'playback_trend' => $this->playbackMonitor->dailyBreakdown($today->subDays(13), $today),
            'zones' => $this->zones->withDeviceCounts(),
            'next_friday' => $this->fridaySchedules->nextUpcoming(),
            'unscheduled_fridays' => $this->fridaySchedules->unscheduledFridays(),
            'upcoming_sessions' => $this->sessions->upcoming(5),
            'attendance_trend' => $this->attendances->monthlyTrend(6),
            'attendance_rate' => $this->attendances->monthlyTrend(1)[0]['rate'] ?? 0.0,
            'sessions_this_month' => $this->sessions->between($monthStart, $today->endOfMonth())->count(),
        ];
    }

    /**
     * A mentor only sees their own halaqah, plus the shared prayer information.
     *
     * @return array<string, mixed>
     */
    public function forMentor(User $mentor): array
    {
        $today = DateHelper::today();
        $groupIds = $this->sessions->accessibleGroupIds($mentor);

        return [
            'schedule' => $this->prayerSchedules->today(),
            'next_prayer' => $this->prayerSchedules->nextPrayer(),
            'hijri_date' => DateHelper::formatHijri($today),
            'next_friday' => $this->fridaySchedules->nextUpcoming(),
            'upcoming_sessions' => $this->sessions->upcoming(5, $groupIds),
            'attendance_trend' => $this->attendances->monthlyTrend(6, $groupIds),
            'attendance_rate' => $this->attendances->monthlyTrend(1, $groupIds)[0]['rate'] ?? 0.0,
            'group_recap' => $this->attendances->recapByGroup($today->startOfMonth(), $today->endOfMonth(), $groupIds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(User $employee): array
    {
        $today = DateHelper::today();

        return [
            'schedule' => $this->prayerSchedules->today(),
            'next_prayer' => $this->prayerSchedules->nextPrayer(),
            'hijri_date' => DateHelper::formatHijri($today),
            'next_friday' => $this->fridaySchedules->nextUpcoming(),
            'upcoming_sessions' => $this->sessions->upcomingForMember($employee, 5),
            'my_attendance' => $this->attendances->recapByMember(
                $today->subMonths(3)->startOfMonth(),
                $today->endOfMonth(),
            ),
        ];
    }
}
