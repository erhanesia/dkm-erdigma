<?php

declare(strict_types=1);

namespace App\Services\Prayer;

use App\Enums\PrayerName;
use App\Exceptions\BusinessRuleException;
use App\Services\SettingService;
use App\Support\Helpers\DateHelper;

/**
 * Aligns the locally calculated schedule with the officially published one.
 *
 * The calculation and the Kemenag tables use the same astronomy but differ by a
 * couple of minutes, mostly because the published tables round outward and add
 * their own safety margin (ihtiyati). Rather than guess that margin, this
 * measures it: fetch the official times for a stretch of days, compare each
 * prayer against our own figure, and keep the offset that reconciles them.
 *
 * The API is touched only here. Once the offsets are stored, the schedule is
 * generated offline exactly as before — so a network outage can never stop the
 * adhan, it can only postpone re-calibration.
 */
class PrayerCalibrationService
{
    /**
     * Days sampled. A single day can be a minute out purely from rounding, so
     * several are compared and the most frequent offset wins.
     */
    private const SAMPLE_DAYS = 14;

    /** Beyond this, the mismatch is not a rounding margin but the wrong city. */
    private const IMPLAUSIBLE_OFFSET_MINUTES = 25;

    public function __construct(
        private readonly OfficialScheduleClient $official,
        private readonly PrayerTimeCalculator $calculator,
        private readonly PrayerScheduleService $schedules,
        private readonly SettingService $settings,
    ) {}

    /**
     * Compare local against official without changing anything.
     *
     * @return array{
     *     city: string|null,
     *     sampled_days: int,
     *     rows: array<int, array{prayer: string, label: string, local: string, official: string, offset: int, current: int}>,
     *     max_offset: int
     * }
     */
    public function preview(string $cityId): array
    {
        $samples = $this->collectSamples($cityId);

        if ($samples['days'] === 0) {
            throw new BusinessRuleException(
                'Jadwal resmi untuk daerah itu tidak bisa diambil. Coba lagi, atau pilih daerah lain.',
                503,
            );
        }

        $current = $this->calculator->adjustments();
        $rows = [];
        $maxOffset = 0;

        foreach (PrayerName::cases() as $prayer) {
            $offsets = $samples['offsets'][$prayer->value] ?? [];

            if ($offsets === []) {
                continue;
            }

            $offset = $this->mostFrequent($offsets);
            $maxOffset = max($maxOffset, abs($offset));

            $rows[] = [
                'prayer' => $prayer->value,
                'label' => $prayer->label(),
                'local' => $samples['local'][$prayer->value] ?? '--:--',
                'official' => $samples['official'][$prayer->value] ?? '--:--',
                'offset' => $offset,
                'current' => $current[$prayer->value] ?? 0,
            ];
        }

        return [
            'city' => $samples['city'],
            'sampled_days' => $samples['days'],
            'rows' => $rows,
            'max_offset' => $maxOffset,
        ];
    }

    /**
     * Apply the measured offsets and regenerate the upcoming schedule.
     *
     * @return array{applied: int, days: int, city: string|null}
     */
    public function apply(string $cityId): array
    {
        $preview = $this->preview($cityId);

        if ($preview['max_offset'] > self::IMPLAUSIBLE_OFFSET_MINUTES) {
            throw new BusinessRuleException(sprintf(
                'Selisihnya sampai %d menit — terlalu jauh untuk sekadar koreksi ihtiyati. '
                .'Kemungkinan koordinat atau daerah yang dipilih tidak cocok. Periksa dulu sebelum dikalibrasi.',
                $preview['max_offset'],
            ));
        }

        $values = ['prayer.official_city_id' => $cityId];

        foreach ($preview['rows'] as $row) {
            $values['prayer.adjustment.'.$row['prayer']] = (string) $row['offset'];
        }

        $this->settings->save($values);

        $days = $this->schedules->regenerateAutomatic();

        return [
            'applied' => count($preview['rows']),
            'days' => $days,
            'city' => $preview['city'],
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function searchCities(string $keyword): array
    {
        return $this->official->searchCities($keyword);
    }

    /**
     * Walks the sample window, collecting the difference per prayer per day.
     *
     * @return array{
     *     days: int,
     *     city: string|null,
     *     offsets: array<string, array<int, int>>,
     *     local: array<string, string>,
     *     official: array<string, string>
     * }
     */
    private function collectSamples(string $cityId): array
    {
        $start = DateHelper::today();
        $offsets = [];
        $localToday = [];
        $officialToday = [];
        $days = 0;

        for ($i = 0; $i < self::SAMPLE_DAYS; $i++) {
            $date = $start->addDays($i);
            $official = $this->official->timingsFor($cityId, $date);

            if ($official === null) {
                continue;
            }

            // Raw calculation, before any stored adjustment — otherwise the
            // measurement would be contaminated by the value being replaced.
            $local = $this->calculator->calculateRaw($date);
            $days++;

            foreach ($official as $prayer => $officialTime) {
                if (! isset($local[$prayer])) {
                    continue;
                }

                $localTime = substr($local[$prayer], 0, 5);
                $offsets[$prayer][] = $this->minutesBetween($localTime, $officialTime);

                if ($i === 0) {
                    $localToday[$prayer] = $localTime;
                    $officialToday[$prayer] = $officialTime;
                }
            }
        }

        return [
            'days' => $days,
            'city' => $this->official->cityLabel($cityId, $start),
            'offsets' => $offsets,
            'local' => $localToday,
            'official' => $officialToday,
        ];
    }

    private function minutesBetween(string $from, string $to): int
    {
        [$fh, $fm] = array_map('intval', explode(':', $from));
        [$th, $tm] = array_map('intval', explode(':', $to));

        return ($th * 60 + $tm) - ($fh * 60 + $fm);
    }

    /**
     * The offset seen most often across the sampled days.
     *
     * A plain average would land on fractions the settings cannot store, and a
     * single day can be a minute out from rounding alone; the mode ignores both.
     *
     * @param  array<int, int>  $values
     */
    private function mostFrequent(array $values): int
    {
        $counts = array_count_values($values);
        arsort($counts);

        return (int) array_key_first($counts);
    }
}
