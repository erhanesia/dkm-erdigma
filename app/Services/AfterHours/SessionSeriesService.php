<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Exceptions\BusinessRuleException;
use App\Models\AfterHoursSession;
use App\Models\AfterHoursSessionSeries;
use App\Models\User;
use App\Repositories\Contracts\AfterHoursSessionRepositoryInterface;
use App\Repositories\Contracts\AfterHoursSessionSeriesRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Standing appointments: one form that schedules a halaqah's meetings every
 * one or two weeks until a chosen date.
 *
 * Every meeting is created up front, through the same path as a session
 * scheduled on its own, so each gets its QR token and its attendance list at
 * once — and nothing needs a scheduler to create next week's meeting later.
 */
class SessionSeriesService
{
    /**
     * The longest a series may run: long enough for a term of halaqah, short
     * enough that a mistyped end date cannot fill years of the calendar.
     */
    public const MAX_MONTHS = 6;

    /**
     * Every week, or every two weeks — the latter being how most halaqah meet.
     *
     * @var array<int, int>
     */
    public const INTERVALS = [1, 2];

    public function __construct(
        private readonly AfterHoursSessionService $sessions,
        private readonly AfterHoursSessionRepositoryInterface $sessionRecords,
        private readonly AfterHoursSessionSeriesRepositoryInterface $series,
    ) {}

    /**
     * Creates the series and every meeting in it.
     *
     * A date on which the halaqah already has a session is skipped rather than
     * doubled: halaqah that met weekly before series existed would otherwise get
     * two meetings on the same evening.
     *
     * @param  array<string, mixed>  $attributes  The first meeting, as a single session would be scheduled.
     * @return array{series: AfterHoursSessionSeries, sessions: Collection<int, AfterHoursSession>, skipped: array<int, string>}
     */
    public function schedule(array $attributes, int $everyWeeks, CarbonImmutable $until, User $actor): array
    {
        $firstStart = CarbonImmutable::parse((string) $attributes['starts_at']);
        $firstEnd = CarbonImmutable::parse((string) $attributes['ends_at']);

        $this->guardPattern($firstStart, $firstEnd, $everyWeeks, $until);

        $dates = $this->occurrenceDates($firstStart, $everyWeeks, $until);
        $taken = $this->sessionRecords->datesTakenByGroup((int) $attributes['mentoring_group_id'], $firstStart, $until);
        $free = array_values(array_diff($dates, $taken));

        if ($free === []) {
            throw new BusinessRuleException('Semua tanggal pada rentang ini sudah punya kegiatan untuk halaqah tersebut.');
        }

        return DB::transaction(function () use ($attributes, $everyWeeks, $until, $actor, $firstStart, $firstEnd, $dates, $free): array {
            $series = $this->series->create([
                'mentoring_group_id' => (int) $attributes['mentoring_group_id'],
                'topic' => $attributes['topic'],
                'weekday' => $firstStart->dayOfWeekIso,
                'interval_weeks' => $everyWeeks,
                'start_time' => $firstStart->format('H:i:s'),
                'end_time' => $firstEnd->format('H:i:s'),
                'starts_on' => $firstStart->toDateString(),
                'ends_on' => $until->toDateString(),
                'created_by' => $actor->id,
            ]);

            $sessions = collect($free)->map(fn (string $date): AfterHoursSession => $this->sessions->create([
                ...$attributes,
                'series_id' => $series->id,
                'starts_at' => $date.' '.$firstStart->format('H:i:s'),
                'ends_at' => $date.' '.$firstEnd->format('H:i:s'),
            ], $actor));

            return [
                'series' => $series,
                'sessions' => $sessions,
                'skipped' => array_values(array_diff($dates, $free)),
            ];
        });
    }

    /**
     * Every date the pattern falls on, from the first meeting to the end date.
     *
     * The first meeting's own date sets the weekday; stepping whole weeks from
     * it keeps every later one on that same day.
     *
     * @return array<int, string> `Y-m-d`
     */
    public function occurrenceDates(CarbonImmutable $first, int $everyWeeks, CarbonImmutable $until): array
    {
        $dates = [];
        $last = $until->startOfDay();

        for ($date = $first->startOfDay(); $date->lessThanOrEqualTo($last); $date = $date->addWeeks($everyWeeks)) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * The last date a series starting on this day may run to.
     *
     * Without overflow, so a series starting on 31 August ends by the last day
     * of February rather than spilling into March.
     */
    public static function latestEndFor(CarbonImmutable $firstStart): CarbonImmutable
    {
        return $firstStart->startOfDay()->addMonthsNoOverflow(self::MAX_MONTHS);
    }

    /**
     * The rules the form checks, kept here too so no other caller can create a
     * series the form would have refused.
     */
    private function guardPattern(CarbonImmutable $firstStart, CarbonImmutable $firstEnd, int $everyWeeks, CarbonImmutable $until): void
    {
        if (! in_array($everyWeeks, self::INTERVALS, true)) {
            throw new BusinessRuleException('Kegiatan berulang hanya bisa setiap minggu atau setiap 2 minggu.');
        }

        if (! $firstEnd->isSameDay($firstStart) || $firstEnd->lessThanOrEqualTo($firstStart)) {
            throw new BusinessRuleException('Kegiatan berulang harus selesai di hari yang sama, setelah waktu mulainya.');
        }

        if ($until->startOfDay()->lessThan($firstStart->startOfDay())) {
            throw new BusinessRuleException('Tanggal akhir pengulangan tidak boleh sebelum kegiatan pertama.');
        }

        if ($until->startOfDay()->greaterThan(self::latestEndFor($firstStart))) {
            throw new BusinessRuleException('Kegiatan berulang paling lama '.self::MAX_MONTHS.' bulan dari kegiatan pertama.');
        }
    }
}
