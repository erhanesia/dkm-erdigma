<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AudioZone;
use App\Repositories\Contracts\ZonePrayerSettingRepositoryInterface;
use Illuminate\Database\Seeder;

/**
 * The rooms named in the brief, including the CEO room that was the whole point
 * of making tilawah configurable per zone.
 */
class AudioZoneSeeder extends Seeder
{
    public function run(ZonePrayerSettingRepositoryInterface $prayerSettings): void
    {
        foreach ($this->zones() as $attributes) {
            $zone = AudioZone::query()->firstOrCreate(
                ['code' => $attributes['code']],
                $attributes,
            );

            $prayerSettings->seedDefaults($zone);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function zones(): array
    {
        return [
            [
                'code' => 'musholla',
                'name' => 'Musholla',
                'floor' => 'Lantai 1',
                'description' => 'Ruang sholat utama.',
                'default_volume' => 90,
                'is_adhan_enabled' => true,
                'is_murottal_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'lobby',
                'name' => 'Lobby',
                'floor' => 'Lantai 1',
                'description' => 'Area penerima tamu.',
                'default_volume' => 70,
                'is_adhan_enabled' => true,
                'is_murottal_enabled' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'ruang-kerja',
                'name' => 'Ruang Kerja',
                'floor' => 'Lantai 2',
                'description' => 'Area kerja karyawan.',
                'default_volume' => 60,
                'is_adhan_enabled' => true,
                'is_murottal_enabled' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'ruang-ceo',
                'name' => 'Ruangan CEO',
                'floor' => 'Lantai 3',
                'description' => 'Adzan tetap menyala, tilawah dimatikan sesuai permintaan.',
                'default_volume' => 55,
                'is_adhan_enabled' => true,
                // The specific complaint from the brief, now an explicit setting.
                'is_murottal_enabled' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'ruang-meeting',
                'name' => 'Ruang Meeting',
                'floor' => 'Lantai 3',
                'description' => 'Adzan menyala, tilawah dimatikan agar tidak mengganggu rapat.',
                'default_volume' => 55,
                'is_adhan_enabled' => true,
                'is_murottal_enabled' => false,
                'sort_order' => 5,
            ],
        ];
    }
}
