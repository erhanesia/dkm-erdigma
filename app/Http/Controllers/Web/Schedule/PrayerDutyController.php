<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Friday\PrayerDutyService;
use App\Services\Prayer\PrayerScheduleService;
use App\Services\User\UserService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The daily muezzin roster, edited a week at a time as one grid.
 */
class PrayerDutyController extends Controller
{
    public function __construct(
        private readonly PrayerDutyService $duties,
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly UserService $users,
    ) {}

    public function index(Request $request): View
    {
        $weekStart = $this->resolveWeekStart($request);

        return view('pages.prayer-duties.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->addDays(6),
            'grid' => $this->duties->weekGrid($weekStart),
            'prayerTimes' => $this->prayerSchedules
                ->forMonth($weekStart)
                ->keyBy(static fn ($schedule): string => DateHelper::toCarbon($schedule->date)->toDateString()),
            'prayers' => PrayerName::withAdhan(),
            'people' => $this->users->dutyCandidateOptions(),
            'statuses' => DutyStatus::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'duties' => ['required', 'array'],
            'duties.*' => ['array'],
            'duties.*.*.muadzin_id' => ['nullable', 'integer', 'exists:users,id'],
            'duties.*.*.imam_id' => ['nullable', 'integer', 'exists:users,id'],
            'duties.*.*.status' => ['nullable', 'in:'.implode(',', DutyStatus::values())],
            'duties.*.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $this->duties->saveWeek($this->onlyKnownPrayers($validated['duties']), $actor);

        Flash::success('Jadwal petugas sholat berhasil disimpan.');

        return redirect()->route('prayer-duties.index', [
            'week' => DateHelper::toCarbon($validated['week_start'])->toDateString(),
        ]);
    }

    private function resolveWeekStart(Request $request): CarbonImmutable
    {
        $reference = $request->filled('week')
            ? DateHelper::toCarbon($request->string('week')->toString())
            : DateHelper::today();

        return $reference->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * Strips any prayer key the application does not recognise, so a crafted
     * form cannot create rows for a made-up prayer.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $duties
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function onlyKnownPrayers(array $duties): array
    {
        $allowed = array_map(
            static fn (PrayerName $prayer): string => $prayer->value,
            PrayerName::withAdhan(),
        );

        $clean = [];

        foreach ($duties as $date => $prayers) {
            foreach ($prayers as $prayer => $attributes) {
                if (in_array((string) $prayer, $allowed, true)) {
                    $clean[$date][$prayer] = $attributes;
                }
            }
        }

        return $clean;
    }
}
