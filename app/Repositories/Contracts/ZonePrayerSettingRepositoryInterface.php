<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AudioZone;
use App\Models\ZonePrayerSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<ZonePrayerSetting>
 */
interface ZonePrayerSettingRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, ZonePrayerSetting>
     */
    public function forZone(int $zoneId): Collection;

    /**
     * Create the five prayer rows a new zone needs, inheriting zone defaults.
     */
    public function seedDefaults(AudioZone $zone): void;

    /**
     * Bulk-save the settings form, keyed by prayer value.
     *
     * @param  array<string, array<string, mixed>>  $settingsByPrayer
     */
    public function saveForZone(AudioZone $zone, array $settingsByPrayer): void;
}
