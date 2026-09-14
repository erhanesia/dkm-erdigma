<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Friday\PrayerDutyService;
use App\Services\SettingService;
use App\Services\User\UserService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
    public function __construct(
        private readonly PrayerDutyService $duties,
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
            'prayerTimes' => $this->duties->prayerTimesBetween($weekStart, $weekEnd),
            'prayers' => PrayerName::rostered(),
            'people' => $this->users->dutyCandidateOptions(),
            'statuses' => DutyStatus::options(),
        ]);
    }

    /**
     * The printout's preview.
     *
     * The range form and the sheet belong to `App\Livewire\PrayerDutyPrintPreview`,
     * which redraws the sheet in place when a new range is applied rather than
     * reloading the page. With no range given it is the current week; the
     * editor's print button passes the week it is showing.
     */
    public function print(): View
    {
        return view('pages.prayer-duties.print');
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
     *
     * @throws ValidationException
     */
    public function pdf(Request $request, SettingService $settings): Response
    {
        [$from, $to] = $this->duties->printPeriod($request->only(['from', 'to']));

        return Pdf::loadView('pages.prayer-duties.pdf', [
            ...$this->duties->printSheet($from, $to),
            'from' => $from,
            'to' => $to,
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
