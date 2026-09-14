<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Models\PrayerDuty;
use App\Models\PrayerSchedule;
use App\Models\User;
use App\Services\Friday\PrayerDutyService;
use App\Services\Prayer\PrayerScheduleService;
use App\Services\SettingService;
use App\Services\User\UserService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The Dzuhur and Ashar roster — who leads as imam and who calls the adhan —
 * edited a week at a time as one grid, and printed for any range.
 */
class PrayerDutyController extends Controller
{
    /**
     * The longest range one printout may cover.
     *
     * Two months is more than a noticeboard ever holds. The bound matters
     * because reading a range also generates any prayer times missing from it,
     * and an open-ended range would let one request write years of them.
     */
    private const MAX_PRINT_DAYS = 62;

    public function __construct(
        private readonly PrayerDutyService $duties,
        private readonly PrayerScheduleService $prayerSchedules,
        private readonly UserService $users,
    ) {}

    public function index(Request $request): View
    {
        $weekStart = $this->resolveWeekStart($request);
        // Friday: the roster covers the working week only.
        $weekEnd = $weekStart->addDays(4);

        return view('pages.prayer-duties.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'grid' => $this->duties->weekGrid($weekStart),
            'prayerTimes' => $this->prayerTimesBetween($weekStart, $weekEnd),
            'prayers' => PrayerName::rostered(),
            'people' => $this->users->dutyCandidateOptions(),
            'statuses' => DutyStatus::options(),
        ]);
    }

    /**
     * The printout's preview: the range form, and the sheet as it will print.
     *
     * With no range given it is the current week. The editor's print button
     * passes the week it is showing, so the sheet opens on what was on screen.
     */
    public function print(Request $request): View
    {
        [$from, $to] = $this->resolvePrintPeriod($request);

        return view('pages.prayer-duties.print', [
            ...$this->sheet($from, $to),
            'maxDays' => self::MAX_PRINT_DAYS,
        ]);
    }

    /**
     * The same sheet as a PDF, opened in the browser to print or to save.
     *
     * Printing the preview page itself left the result to the browser: its own
     * header and footer on the paper, its own margins, a blank first page and a
     * table running off the edge. A PDF is laid out here, so it prints the same
     * from any browser to any printer.
     *
     * Only the glyphs the sheet uses are embedded. The whole of DejaVu Sans put
     * a month's roster near a megabyte; subset, it is a few dozen kilobytes.
     */
    public function pdf(Request $request, SettingService $settings): Response
    {
        [$from, $to] = $this->resolvePrintPeriod($request);

        return Pdf::loadView('pages.prayer-duties.pdf', [
            ...$this->sheet($from, $to),
            'mosqueName' => $settings->mosqueName(),
            'printedAt' => DateHelper::now(),
        ])
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4', 'landscape')
            ->stream(sprintf('petugas-sholat-%s-%s.pdf', $from->toDateString(), $to->toDateString()));
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

        $this->duties->saveWeek($this->onlyRosteredSlots($validated['duties']), $actor);

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
     * Each end defaults to the current working week's, Monday to Friday, and a
     * range typed in backwards is turned around rather than refused — the same
     * leniency as the attendance report's period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws ValidationException
     */
    private function resolvePrintPeriod(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ], [
            'from.date' => 'Dari tanggal harus berupa tanggal yang valid.',
            'to.date' => 'Sampai tanggal harus berupa tanggal yang valid.',
        ]);

        $weekStart = DateHelper::today()->startOfWeek(CarbonInterface::MONDAY);

        $from = $request->filled('from')
            ? DateHelper::toCarbon($request->string('from')->toString())->startOfDay()
            : $weekStart;

        $to = $request->filled('to')
            ? DateHelper::toCarbon($request->string('to')->toString())->startOfDay()
            : $weekStart->addDays(4);

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        if ((int) $from->diffInDays($to) + 1 > self::MAX_PRINT_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'Rentang cetak paling panjang '.self::MAX_PRINT_DAYS.' hari.',
            ]);
        }

        return [$from, $to];
    }

    /**
     * What the preview and the PDF both draw, so the two cannot disagree.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, grid: array<string, array<string, PrayerDuty|null>>, prayerTimes: Collection<string, PrayerSchedule>, prayers: array<int, PrayerName>}
     */
    private function sheet(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'grid' => $this->duties->gridBetween($from, $to),
            'prayerTimes' => $this->prayerTimesBetween($from, $to),
            'prayers' => PrayerName::rostered(),
        ];
    }

    /**
     * Prayer times for the window, keyed by `Y-m-d`.
     *
     * Read by range rather than by month: a week that starts in one month and
     * ends in the next used to lose the times for its last few days.
     *
     * @return Collection<string, PrayerSchedule>
     */
    private function prayerTimesBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->prayerSchedules
            ->forRange($from, $to)
            ->keyBy(static fn (PrayerSchedule $schedule): string => DateHelper::toCarbon($schedule->date)->toDateString());
    }

    /**
     * Strips anything the roster does not cover, so a crafted form cannot
     * create rows for a made-up prayer, for Subuh, Maghrib or Isya, or for a
     * Saturday or Sunday.
     *
     * The dates arrive as array keys, which validation does not reach, so one
     * that is not a plain `Y-m-d` is dropped before it is parsed.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $duties
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function onlyRosteredSlots(array $duties): array
    {
        $allowed = array_map(
            static fn (PrayerName $prayer): string => $prayer->value,
            PrayerName::rostered(),
        );

        $clean = [];

        foreach ($duties as $date => $prayers) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) !== 1
                || ! $this->duties->isRosteredDay(DateHelper::toCarbon((string) $date))) {
                continue;
            }

            foreach ($prayers as $prayer => $attributes) {
                if (in_array((string) $prayer, $allowed, true)) {
                    $clean[$date][$prayer] = $attributes;
                }
            }
        }

        return $clean;
    }
}
