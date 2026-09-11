<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Monitoring;

use App\Enums\PlaybackStatus;
use App\Enums\PlaybackType;
use App\Http\Controllers\Controller;
use App\Models\PlaybackLog;
use App\Models\User;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Services\Audio\AudioZoneService;
use App\Services\Device\DeviceService;
use App\Services\Device\PlaybackMonitorService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page that answers "adzan tadi kedengeran nggak?".
 *
 * Every scheduled adhan is reserved as `pending` up front, so this listing shows
 * not only what failed but also what never reported at all — the case the old
 * local script could not surface.
 */
class PlaybackLogController extends Controller
{
    public function __construct(
        private readonly PlaybackMonitorService $monitor,
        private readonly PlaybackLogRepositoryInterface $playbackLogs,
        private readonly AudioZoneService $zones,
        private readonly DeviceService $devices,
    ) {}

    public function index(Request $request): View
    {
        $to = $request->filled('date_to')
            ? DateHelper::toCarbon($request->string('date_to')->toString())
            : DateHelper::today();

        $from = $request->filled('date_from')
            ? DateHelper::toCarbon($request->string('date_from')->toString())
            : $to->subDays(29);

        return view('pages.playback-logs.index', [
            'logs' => $this->playbackLogs->paginateFiltered(),
            'summary' => $this->monitor->summary($from, $to),
            'trend' => $this->monitor->dailyBreakdown($from->max($to->subDays(29)), $to),
            'byZone' => $this->monitor->reliabilityByZone($from, $to),
            'issues' => $this->monitor->unresolvedIssues(10),
            'deviceHealth' => $this->devices->healthSummary(),
            'zones' => $this->zones->options(),
            'statuses' => PlaybackStatus::options(),
            'types' => PlaybackType::options(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Marks one issue as handled so it drops off the outstanding list.
     */
    public function acknowledge(Request $request, PlaybackLog $playbackLog): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->monitor->acknowledge($playbackLog, $actor);

        Flash::success('Masalah pemutaran ditandai sudah ditindaklanjuti.');

        return back();
    }

    public function acknowledgeAll(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $count = $this->monitor->acknowledgeAll($actor);

        Flash::success($count.' masalah pemutaran ditandai sudah ditindaklanjuti.');

        return back();
    }
}
