<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum AudioTrackType: string implements HasLabel
{
    use EnumHelpers;

    case Adhan = 'adhan';
    case AdhanFajr = 'adhan_fajr';
    case Iqamah = 'iqamah';
    case Tarhim = 'tarhim';
    case Murottal = 'murottal';
    case Announcement = 'announcement';
    case TestTone = 'test_tone';

    public function label(): string
    {
        return match ($this) {
            self::Adhan => 'Adzan',
            self::AdhanFajr => 'Adzan Subuh',
            self::Iqamah => 'Iqamah',
            self::Tarhim => 'Tarhim',
            self::Murottal => 'Murottal / Tilawah',
            self::Announcement => 'Pengumuman',
            self::TestTone => 'Nada Uji Speaker',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Adhan, self::AdhanFajr => 'primary',
            self::Iqamah => 'success',
            self::Tarhim => 'info',
            self::Murottal => 'teal',
            self::Announcement => 'warning',
            self::TestTone => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Adhan, self::AdhanFajr => 'megaphone',
            self::Iqamah => 'bell',
            self::Tarhim => 'music-note-beamed',
            self::Murottal => 'book',
            self::Announcement => 'broadcast',
            self::TestTone => 'soundwave',
        };
    }

    /**
     * Track types eligible to be attached to a prayer adhan slot.
     *
     * @return array<int, self>
     */
    public static function adhanTypes(): array
    {
        return [self::Adhan, self::AdhanFajr];
    }
}
