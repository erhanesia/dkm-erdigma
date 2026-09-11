<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PrayerName;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the editable runtime settings with the values from `config/dkm.php`.
 *
 * Existing rows keep their value, so re-running the seeder never overwrites a
 * setting the DKM board has already tuned.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            Setting::query()->firstOrCreate(
                ['key' => $definition['key']],
                $definition,
            );
        }
    }

    /**
     * @return array<int, array{key: string, value: string|null, type: string, group: string, label: string, description: string|null}>
     */
    private function definitions(): array
    {
        $settings = [
            [
                'key' => 'prayer.official_city_id',
                'value' => (string) config('dkm.official_schedule.city_id'),
                'type' => 'string',
                'group' => 'prayer',
                'label' => 'Kota Jadwal Resmi',
                'description' => 'Jadwal Kemenag untuk kabupaten/kota ini dipakai sebagai sumber utama. Kosongkan untuk memakai perhitungan lokal saja.',
            ],
            [
                'key' => 'mosque.name',
                'value' => (string) config('dkm.mosque.name'),
                'type' => 'string',
                'group' => 'mosque',
                'label' => 'Nama Masjid / Musholla',
                'description' => 'Ditampilkan di header aplikasi dan layar player.',
            ],
            [
                'key' => 'mosque.address',
                'value' => (string) config('dkm.mosque.address'),
                'type' => 'string',
                'group' => 'mosque',
                'label' => 'Alamat',
                'description' => null,
            ],
            [
                'key' => 'prayer.latitude',
                'value' => (string) config('dkm.prayer.latitude'),
                'type' => 'float',
                'group' => 'prayer',
                'label' => 'Lintang (Latitude)',
                'description' => 'Koordinat lokasi masjid. Salah sedikit saja jadwal bisa meleset beberapa menit.',
            ],
            [
                'key' => 'prayer.longitude',
                'value' => (string) config('dkm.prayer.longitude'),
                'type' => 'float',
                'group' => 'prayer',
                'label' => 'Bujur (Longitude)',
                'description' => null,
            ],
            [
                'key' => 'prayer.elevation',
                'value' => (string) config('dkm.prayer.elevation'),
                'type' => 'float',
                'group' => 'prayer',
                'label' => 'Ketinggian (mdpl)',
                'description' => 'Memengaruhi waktu terbit dan maghrib.',
            ],
            [
                'key' => 'prayer.calculation_method',
                'value' => (string) config('dkm.prayer.calculation_method'),
                'type' => 'string',
                'group' => 'prayer',
                'label' => 'Metode Hisab',
                'description' => 'Kemenag RI mengikuti jadwal resmi Kementerian Agama.',
            ],
            [
                'key' => 'prayer.asr_method',
                'value' => (string) config('dkm.prayer.asr_method'),
                'type' => 'string',
                'group' => 'prayer',
                'label' => 'Mazhab Ashar',
                'description' => 'Standar untuk mayoritas di Indonesia.',
            ],
        ];

        /** @var array<string, int> $adjustments */
        $adjustments = config('dkm.prayer.adjustments', []);

        foreach (PrayerName::cases() as $prayer) {
            $settings[] = [
                'key' => 'prayer.adjustment.'.$prayer->value,
                'value' => (string) ($adjustments[$prayer->value] ?? 0),
                'type' => 'integer',
                'group' => 'prayer',
                'label' => 'Koreksi '.$prayer->label().' (menit)',
                'description' => 'Ihtiyati — selisih menit dari hasil hitung.',
            ];
        }

        return $settings;
    }
}
