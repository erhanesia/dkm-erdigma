<?php

declare(strict_types=1);

namespace App\Services\Friday;

use App\Enums\PrayerName;
use App\Models\PrayerDuty;
use App\Models\PrayerSchedule;
use App\Models\User;
use App\Repositories\Contracts\PrayerDutyRepositoryInterface;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The Dzuhur and Ashar roster for the working week — imam and muadzin —
 * edited a week at a time and printed for any range.
 */
class PrayerDutyService
{
    /**
     * The longest range one printout may cover.
     *
     * Two months is more than a noticeboard ever holds. The bound matters
     * because reading a range also generates any prayer times missing from it,
     * and an open-ended range would let one request write years of them.
     */
    public const MAX_PRINT_DAYS = 62;

    public function __construct(
        private readonly PrayerDutyRepositoryInterface $duties,
        private readonly PrayerScheduleService $prayerSchedules,
    ) {}

    /**
     * The working week shaped for the Blade table: date => prayer => duty|null.
     *
     * @return array<string, array<string, PrayerDuty|null>>
     */
    public function weekGrid(CarbonImmutable $weekStart): array
    {
        return $this->gridBetween($weekStart, $weekStart->addDays(4));
    }

    /**
     * The same shape for any window — the editor asks for a week, the printed
     * roster for whatever range the board picks.
     *
     * Every rostered day in the window gets a row and every rostered prayer a
     * column, filled or not, so a gap in the roster shows up as a gap on the
     * sheet.
     *
     * @return array<string, array<string, PrayerDuty|null>>
     */
    public function gridBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $duties = $this->duties->between($from, $to)->keyBy(
            static fn (PrayerDuty $duty): string => DateHelper::toCarbon($duty->date)->toDateString().'|'.$duty->prayer->value,
        );

        $grid = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            if (! $this->isRosteredDay($cursor)) {
                continue;
            }

            $day = $cursor->toDateString();

            foreach (PrayerName::rostered() as $prayer) {
                $grid[$day][$prayer->value] = $duties->get($day.'|'.$prayer->value);
            }
        }

        return $grid;
    }

    /**
     * Whether officers are assigned on this day: Monday to Friday.
     *
     * These are the office's congregations, and nobody is in on the weekend —
     * Saturday is work-from-anywhere and Sunday a day off — so there is no one
     * on site to lead either prayer or to call it.
     */
    public function isRosteredDay(CarbonInterface $date): bool
    {
        return $date->isWeekday();
    }

    /**
     * The range a printout covers, from the address bar or the preview's form.
     *
     * Each end defaults to the current working week's, Monday to Friday, and a
     * range typed in backwards is turned around rather than refused — the same
     * leniency as the attendance report's period.
     *
     * Decided here rather than by each caller: the preview and the PDF both take
     * a range, and one the preview accepts the PDF must not refuse.
     *
     * @param  array{from?: mixed, to?: mixed}  $input
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws ValidationException
     */
    public function printPeriod(array $input): array
    {
        $validated = Validator::make($input, [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ], [
            'from.date' => 'Dari tanggal harus berupa tanggal yang valid.',
            'to.date' => 'Sampai tanggal harus berupa tanggal yang valid.',
        ])->validate();

        $weekStart = DateHelper::today()->startOfWeek(CarbonInterface::MONDAY);

        $from = filled($validated['from'] ?? null)
            ? DateHelper::toCarbon((string) $validated['from'])->startOfDay()
            : $weekStart;

        $to = filled($validated['to'] ?? null)
            ? DateHelper::toCarbon((string) $validated['to'])->startOfDay()
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
     * @return array{grid: array<string, array<string, PrayerDuty|null>>, prayerTimes: Collection<string, PrayerSchedule>, prayers: array<int, PrayerName>}
     */
    public function printSheet(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'grid' => $this->gridBetween($from, $to),
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
    public function prayerTimesBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->prayerSchedules
            ->forRange($from, $to)
            ->keyBy(static fn (PrayerSchedule $schedule): string => DateHelper::toCarbon($schedule->date)->toDateString());
    }

    /**
     * Persist the whole week in one submit.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $grid
     */
    public function saveWeek(array $grid, User $actor): void
    {
        $this->duties->saveGrid($grid, $actor->id);
    }

    /**
     * @return Collection<int, PrayerDuty>
     */
    public function forDate(CarbonImmutable|string $date): Collection
    {
        return $this->duties->forDate($date);
    }

    /**
     * @return Collection<int, PrayerDuty>
     */
    public function upcomingForUser(User $user, int $limit = 5): Collection
    {
        return $this->duties->upcomingForUser($user->id, $limit);
    }

    /**
     * Muezzin on duty for a given prayer today, shown next to the countdown.
     */
    public function muadzinFor(PrayerName $prayer, ?CarbonImmutable $date = null): ?User
    {
        return $this->duties->findFor($date ?? DateHelper::today(), $prayer)?->muadzin;
    }
}
