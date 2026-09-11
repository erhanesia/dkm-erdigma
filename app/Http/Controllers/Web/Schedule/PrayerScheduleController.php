<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Prayer\PrayerScheduleService;
use App\Services\Prayer\PrayerTimeCalculator;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerScheduleController extends Controller
{
    public function __construct(
        private readonly PrayerScheduleService $schedules,
        private readonly PrayerTimeCalculator $calculator,
    ) {}

    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? DateHelper::toCarbon($request->string('month')->toString().'-01')
            : DateHelper::today();

        return view('pages.prayer-schedules.index', [
            'month' => $month,
            'schedules' => $this->schedules->forMonth($month),
            'today' => $this->schedules->today(),
            'nextPrayer' => $this->schedules->nextPrayer(),
            'prayers' => PrayerName::cases(),
            'method' => $this->calculator->method(),
            'methods' => $this->calculator->availableMethods(),
        ]);
    }

    /**
     * Hand-corrects one day. The day is then flagged as an override so the
     * nightly generator leaves it alone.
     */
    public function update(Request $request, string $date): RedirectResponse
    {
        $validated = $request->validate([
            'fajr' => ['required', 'date_format:H:i'],
            'sunrise' => ['required', 'date_format:H:i'],
            'dhuhr' => ['required', 'date_format:H:i'],
            'asr' => ['required', 'date_format:H:i'],
            'maghrib' => ['required', 'date_format:H:i'],
            'isha' => ['required', 'date_format:H:i'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $this->schedules->overrideDay($date, $validated, $actor->id);

        Flash::success('Jadwal '.DateHelper::formatDate($date).' berhasil diubah manual. '
            .'Tanggal ini tidak akan ditimpa oleh perhitungan otomatis.');

        return back();
    }

    /**
     * Drops the manual override and returns the day to the calculated times.
     */
    public function reset(string $date): RedirectResponse
    {
        $this->schedules->resetDay($date);

        Flash::success('Jadwal '.DateHelper::formatDate($date).' dikembalikan ke hasil perhitungan otomatis.');

        return back();
    }

    /**
     * Fills the horizon on demand, for when the scheduler has not run yet.
     */
    public function generate(): RedirectResponse
    {
        $written = $this->schedules->ensureHorizon();

        Flash::success($written === 0
            ? 'Jadwal sudah lengkap, tidak ada yang perlu dibuat.'
            : $written.' hari jadwal sholat berhasil dibuat.');

        return back();
    }
}
