<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Services\AfterHours\AttendanceService;
use App\Services\AfterHours\MentoringGroupService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Attendance reporting for after-hours mentoring.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendances,
        private readonly MentoringGroupService $groups,
        private readonly AfterHoursSessionService $sessions,
    ) {}

    public function attendance(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        [$from, $to] = $this->resolvePeriod($request);
        $groupIds = $this->scopedGroupIds($user);

        return view('pages.reports.attendance', [
            'from' => $from,
            'to' => $to,
            'byMember' => $this->attendances->recapByMember($from, $to, $groupIds),
            'byGroup' => $this->attendances->recapByGroup($from, $to, $groupIds),
            'trend' => $this->attendances->monthlyTrend(6, $groupIds),
            'sessions' => $this->sessions->between($from, $to, $groupIds),
            'groups' => $this->groups->options($user->isAdministrator() ? null : $user),
        ]);
    }

    /**
     * Excel export of the same recap.
     *
     * Not wired to a spreadsheet writer yet — the export classes are the next
     * milestone, so for now this states plainly that it is unavailable rather
     * than returning an empty file.
     */
    public function exportAttendance(Request $request): RedirectResponse
    {
        Flash::info(
            'Ekspor Excel sedang disiapkan. Untuk sementara silakan gunakan tampilan rekap di layar.',
        );

        return back();
    }

    /**
     * Defaults to the current month when no range is given.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->filled('from')
            ? DateHelper::toCarbon($request->string('from')->toString())->startOfDay()
            : DateHelper::today()->startOfMonth();

        $to = $request->filled('to')
            ? DateHelper::toCarbon($request->string('to')->toString())->endOfDay()
            : DateHelper::today()->endOfMonth();

        return $to->lessThan($from) ? [$to, $from] : [$from, $to];
    }

    /**
     * @return array<int, int>|null
     */
    private function scopedGroupIds(User $user): ?array
    {
        return $user->isAdministrator() ? null : $this->sessions->accessibleGroupIds($user);
    }
}
