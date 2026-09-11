<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Prayer\PrayerScheduleService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Runtime configuration the DKM board can change without a deploy.
 *
 * Changing anything that affects prayer time calculation regenerates the
 * upcoming schedule, so a corrected latitude takes effect the same day.
 */
class SettingService
{
    /** @var array<int, string> */
    private const PRAYER_KEYS = [
        'prayer.latitude',
        'prayer.longitude',
        'prayer.elevation',
        'prayer.calculation_method',
        'prayer.asr_method',
        'prayer.adjustment.fajr',
        'prayer.adjustment.sunrise',
        'prayer.adjustment.dhuhr',
        'prayer.adjustment.asr',
        'prayer.adjustment.maghrib',
        'prayer.adjustment.isha',
    ];

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly PrayerScheduleService $prayerSchedules,
    ) {}

    /**
     * @return Collection<int, Setting>
     */
    public function group(string $group): Collection
    {
        return $this->settings->grouped($group);
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function all(): array
    {
        return $this->settings->allValues();
    }

    public function get(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null
    {
        return $this->settings->get($key, $default);
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $values
     */
    public function save(array $values): void
    {
        $this->settings->putMany($values);

        if (array_intersect(array_keys($values), self::PRAYER_KEYS) !== []) {
            $this->prayerSchedules->regenerateAutomatic();
        }
    }

    public function mosqueName(): string
    {
        return (string) $this->get('mosque.name', config('dkm.mosque.name'));
    }
}
