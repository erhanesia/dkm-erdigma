<?php

declare(strict_types=1);

namespace App\Services\Prayer;

use App\Enums\PrayerName;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use IslamicNetwork\PrayerTimes\PrayerTimes;

/**
 * Thin wrapper over islamic-network/prayer-times.
 *
 * Calculation happens offline, so the schedule never depends on an external API
 * being reachable — the on-premise setup could at least be trusted for that, and
 * moving to the cloud must not make it worse.
 */
class PrayerTimeCalculator
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {}

    /**
     * Prayer name => `HH:MM:SS` for the given day, with the stored per-prayer
     * corrections applied.
     *
     * @return array<string, string>
     */
    public function calculate(CarbonImmutable $date): array
    {
        $adjustments = $this->adjustments();
        $timings = [];

        foreach ($this->calculateRaw($date) as $prayer => $time) {
            $timings[$prayer] = DateHelper::combine($date, $time)
                ->addMinutes($adjustments[$prayer] ?? 0)
                ->format('H:i:s');
        }

        return $timings;
    }

    /**
     * The astronomical result on its own, before any correction.
     *
     * Calibration measures against this: comparing the official schedule with an
     * already-corrected figure would fold the old correction into the new one.
     *
     * @return array<string, string>
     */
    public function calculateRaw(CarbonImmutable $date): array
    {
        $engine = new PrayerTimes($this->method(), $this->asrMethod());

        $raw = $engine->getTimes(
            $date->toDateTime(),
            $this->latitude(),
            $this->longitude(),
            $this->elevation(),
            PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
            PrayerTimes::MIDNIGHT_MODE_STANDARD,
            PrayerTimes::TIME_FORMAT_24H,
        );

        $timings = [];

        foreach (PrayerName::cases() as $prayer) {
            $time = $raw[$prayer->libraryKey()] ?? null;

            if ($time === null || $time === PrayerTimes::INVALID_TIME) {
                continue;
            }

            $timings[$prayer->value] = $time;
        }

        return $timings;
    }

    /**
     * @return array<string, string>
     */
    public function calculateForToday(): array
    {
        return $this->calculate(DateHelper::today());
    }

    /**
     * Today's times computed from values that have not been saved.
     *
     * The settings page shows a live preview, and without this the only way to
     * see what a different madhab does to Asr is to save first — which means
     * committing a change in order to find out whether you want it.
     *
     * Nothing is written. Anything the caller omits falls back to what is
     * currently stored, so a partially filled form still previews sensibly.
     *
     * @param  array{
     *     latitude?: float|string|null,
     *     longitude?: float|string|null,
     *     elevation?: float|string|null,
     *     calculation_method?: string|null,
     *     asr_method?: string|null,
     *     adjustments?: array<string, int|string>
     * }  $overrides
     * @return array<string, string>
     */
    public function preview(array $overrides): array
    {
        $engine = new PrayerTimes(
            $this->validMethod($overrides['calculation_method'] ?? null),
            $this->validAsrMethod($overrides['asr_method'] ?? null),
        );

        $raw = $engine->getTimes(
            DateHelper::today()->toDateTime(),
            (float) ($overrides['latitude'] ?? $this->latitude()),
            (float) ($overrides['longitude'] ?? $this->longitude()),
            (float) ($overrides['elevation'] ?? $this->elevation()),
            PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
            PrayerTimes::MIDNIGHT_MODE_STANDARD,
            PrayerTimes::TIME_FORMAT_24H,
        );

        $adjustments = $overrides['adjustments'] ?? $this->adjustments();
        $date = DateHelper::today();
        $timings = [];

        foreach (PrayerName::cases() as $prayer) {
            $time = $raw[$prayer->libraryKey()] ?? null;

            if ($time === null || $time === PrayerTimes::INVALID_TIME) {
                continue;
            }

            $timings[$prayer->value] = DateHelper::combine($date, $time)
                ->addMinutes((int) ($adjustments[$prayer->value] ?? 0))
                ->format('H:i');
        }

        return $timings;
    }

    /**
     * An unknown value from the form would make the library throw; falling back
     * to what is stored keeps the preview answering while someone types.
     */
    private function validMethod(?string $method): string
    {
        return array_key_exists((string) $method, $this->availableMethods())
            ? (string) $method
            : $this->method();
    }

    private function validAsrMethod(?string $method): string
    {
        return array_key_exists((string) $method, $this->availableAsrMethods())
            ? (string) $method
            : $this->asrMethod();
    }

    // -----------------------------------------------------------------
    // Configuration — settings table wins over config defaults
    // -----------------------------------------------------------------

    public function latitude(): float
    {
        return (float) $this->settings->get('prayer.latitude', config('dkm.prayer.latitude'));
    }

    public function longitude(): float
    {
        return (float) $this->settings->get('prayer.longitude', config('dkm.prayer.longitude'));
    }

    public function elevation(): float
    {
        return (float) $this->settings->get('prayer.elevation', config('dkm.prayer.elevation'));
    }

    public function method(): string
    {
        return (string) $this->settings->get('prayer.calculation_method', config('dkm.prayer.calculation_method'));
    }

    public function asrMethod(): string
    {
        return (string) $this->settings->get('prayer.asr_method', config('dkm.prayer.asr_method'));
    }

    /**
     * @return array<string, int>
     */
    public function adjustments(): array
    {
        /** @var array<string, int> $defaults */
        $defaults = config('dkm.prayer.adjustments', []);
        $adjustments = [];

        foreach (PrayerName::cases() as $prayer) {
            $adjustments[$prayer->value] = (int) $this->settings->get(
                'prayer.adjustment.'.$prayer->value,
                $defaults[$prayer->value] ?? 0,
            );
        }

        return $adjustments;
    }

    /**
     * Method value => human label, for the settings dropdown.
     *
     * @return array<string, string>
     */
    public function availableMethods(): array
    {
        return [
            'KEMENAG' => 'Kemenag RI (Indonesia)',
            'MWL' => 'Muslim World League',
            'SINGAPORE' => 'Singapura (MUIS)',
            'JAKIM' => 'Malaysia (JAKIM)',
            'ISNA' => 'ISNA (Amerika Utara)',
            'EGYPT' => 'Egyptian General Authority',
            'MAKKAH' => 'Umm al-Qura, Makkah',
            'KARACHI' => 'University of Islamic Sciences, Karachi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function availableAsrMethods(): array
    {
        return [
            PrayerTimes::SCHOOL_STANDARD => "Standar (Syafi'i, Maliki, Hanbali)",
            PrayerTimes::SCHOOL_HANAFI => 'Hanafi',
        ];
    }
}
