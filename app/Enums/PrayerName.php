<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;
use IslamicNetwork\PrayerTimes\PrayerTimes;

enum PrayerName: string implements HasLabel
{
    use EnumHelpers;

    case Fajr = 'fajr';
    case Sunrise = 'sunrise';
    case Dhuhr = 'dhuhr';
    case Asr = 'asr';
    case Maghrib = 'maghrib';
    case Isha = 'isha';

    public function label(): string
    {
        return match ($this) {
            self::Fajr => 'Subuh',
            self::Sunrise => 'Terbit',
            self::Dhuhr => 'Dzuhur',
            self::Asr => 'Ashar',
            self::Maghrib => 'Maghrib',
            self::Isha => 'Isya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fajr => 'indigo',
            self::Sunrise => 'warning',
            self::Dhuhr => 'primary',
            self::Asr => 'info',
            self::Maghrib => 'orange',
            self::Isha => 'dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Fajr => 'sunrise',
            self::Sunrise => 'brightness-high',
            self::Dhuhr => 'sun',
            self::Asr => 'cloud-sun',
            self::Maghrib => 'sunset',
            self::Isha => 'moon-stars',
        };
    }

    /**
     * The matching key returned by islamic-network/prayer-times.
     */
    public function libraryKey(): string
    {
        return match ($this) {
            self::Fajr => PrayerTimes::FAJR,
            self::Sunrise => PrayerTimes::SUNRISE,
            self::Dhuhr => PrayerTimes::ZHUHR,
            self::Asr => PrayerTimes::ASR,
            self::Maghrib => PrayerTimes::MAGHRIB,
            self::Isha => PrayerTimes::ISHA,
        };
    }

    /**
     * Sunrise is a time marker, not a congregational prayer, so it never gets
     * an adhan and never appears in a muezzin roster.
     */
    public function hasAdhan(): bool
    {
        return $this !== self::Sunrise;
    }

    /**
     * The five prayers that actually get called.
     *
     * @return array<int, self>
     */
    public static function withAdhan(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $prayer): bool => $prayer->hasAdhan(),
        ));
    }
}
