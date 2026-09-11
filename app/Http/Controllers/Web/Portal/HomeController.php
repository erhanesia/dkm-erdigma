<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Services\Friday\FridayScheduleService;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\PublicSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The front page of the public site.
 *
 * A summary of the three pages behind it, so a visitor who only wants "what time
 * is Maghrib" never has to navigate anywhere.
 */
class HomeController extends Controller
{
    /** Enough to be useful without turning the front page into a calendar. */
    private const FRIDAY_PREVIEW = 2;

    private const SESSION_PREVIEW = 3;

    public function __construct(
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly FridayScheduleService $fridaySchedules,
        private readonly AfterHoursSessionService $sessions,
    ) {}

    public function __invoke(Request $request): View
    {
        $today = $this->prayerSchedules->today();
        $next = $this->prayerSchedules->nextPrayer();

        return view('pages.portal.home', [
            'schedule' => $today,
            'timings' => PublicSchedule::timings($today),
            'nextPrayer' => $next['prayer'],
            'nextPrayerAt' => $next['at'],
            'serverTime' => DateHelper::now(),
            'upcomingFridays' => $this->fridaySchedules->upcoming(self::FRIDAY_PREVIEW),
            'upcomingSessions' => $this->sessions->upcomingPublic(self::SESSION_PREVIEW),
        ]);
    }
}
