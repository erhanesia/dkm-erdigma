<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PrayerName;
use App\Models\AudioZone;
use App\Models\ZonePrayerSetting;
use App\Repositories\Contracts\ZonePrayerSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ZonePrayerSetting>
 */
class ZonePrayerSettingRepository extends BaseRepository implements ZonePrayerSettingRepositoryInterface
{
    protected function model(): string
    {
        return ZonePrayerSetting::class;
    }

    public function forZone(int $zoneId): Collection
    {
        return $this->query()
            ->with(['adhanTrack', 'tarhimTrack', 'iqamahTrack'])
            ->where('audio_zone_id', $zoneId)
            ->get();
    }

    public function seedDefaults(AudioZone $zone): void
    {
        foreach (PrayerName::withAdhan() as $prayer) {
            $this->query()->firstOrCreate(
                ['audio_zone_id' => $zone->id, 'prayer' => $prayer->value],
                [
                    'is_adhan_enabled' => $zone->is_adhan_enabled,
                    'volume' => $zone->default_volume,
                    'is_tarhim_enabled' => $prayer === PrayerName::Fajr,
                ],
            );
        }
    }

    public function saveForZone(AudioZone $zone, array $settingsByPrayer): void
    {
        foreach ($settingsByPrayer as $prayer => $attributes) {
            if (PrayerName::tryFrom((string) $prayer) === null) {
                continue;
            }

            $this->query()->updateOrCreate(
                ['audio_zone_id' => $zone->id, 'prayer' => $prayer],
                $attributes,
            );
        }
    }
}
