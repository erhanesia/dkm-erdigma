<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\PublicSchedule;
use Illuminate\View\View;

/**
 * The month's prayer times, for anyone.
 *
 * The same figures the mosque prints and pins to the wall — which is the point:
 * a link that always shows the current month beats a photograph of a printout.
 *
 * What this controller supplies is only the part of the page that is about
 * *this* mosque: today's times and the countdown. The region picker, the month
 * stepper and the table belong to `App\Livewire\PublicPrayerMonth`, which
 * replaces itself rather than the page when a visitor looks somewhere else.
 */
class PrayerScheduleController extends Controller
{
    public function __construct(
        private readonly PrayerScheduleService $prayerSchedules,
    ) {}

    public function __invoke(): View
    {
        $today = $this->prayerSchedules->today();
        $next = $this->prayerSchedules->nextPrayer();

        return view('pages.portal.prayer-schedules', [
            'today' => $today,
            'todayTimings' => PublicSchedule::timings($today),
            'nextPrayer' => $next['prayer'],
            'nextPrayerAt' => $next['at'],
            'schedule' => $today,
            'serverTime' => DateHelper::now(),
        ]);
    }
}
