<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Services\Friday\FridayScheduleService;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use Illuminate\View\View;

/**
 * Who is giving the Friday sermon, publicly.
 *
 * This is the one thing a mosque already announces to everyone — it goes on the
 * noticeboard days in advance — so it belongs on the public site. Only the names
 * and the theme are shown: no contact details, and nothing about who attended.
 */
class FridayScheduleController extends Controller
{
    /** Roughly two months ahead — as far as rosters are normally filled. */
    private const UPCOMING_LIMIT = 8;

    public function __construct(
        private readonly FridayScheduleService $fridaySchedules,
        private readonly PrayerScheduleService $prayerSchedules,
    ) {}

    public function index(): View
    {
        $upcoming = $this->fridaySchedules->upcoming(self::UPCOMING_LIMIT);

        return view('pages.portal.friday-schedules', [
            'schedules' => $upcoming,
            'next' => $upcoming->first(),
            // The Friday sermon starts around Dhuhr, so the time people actually
            // need alongside the roster is today's Dhuhr.
            'todaySchedule' => $this->prayerSchedules->today(),
            'serverTime' => DateHelper::now(),
        ]);
    }

    /**
     * One Friday, addressed by its date.
     *
     * A date reads as itself in a link — /jadwal-jumat/2026-09-11 says what it
     * points at, survives the record being edited, and cannot be enumerated the
     * way a sequential id can.
     */
    public function show(string $date): View
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            abort(404);
        }

        $schedule = $this->fridaySchedules->forDate($date);

        abort_if($schedule === null, 404);

        return view('pages.portal.friday-schedule', [
            'schedule' => $schedule->loadMissing(['khatib', 'imam', 'muadzin']),
            'todaySchedule' => $this->prayerSchedules->today(),
            'others' => $this->fridaySchedules->upcoming(self::UPCOMING_LIMIT)
                ->reject(static fn ($other): bool => $other->id === $schedule->id)
                ->take(3),
        ]);
    }
}
