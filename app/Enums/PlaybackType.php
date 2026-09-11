<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum PlaybackType: string implements HasLabel
{
    use EnumHelpers;

    case Adhan = 'adhan';
    case Iqamah = 'iqamah';
    case Tarhim = 'tarhim';
    case Murottal = 'murottal';
    case Announcement = 'announcement';
    case SelfTest = 'self_test';

    public function label(): string
    {
        return match ($this) {
            self::Adhan => 'Adzan',
            self::Iqamah => 'Iqamah',
            self::Tarhim => 'Tarhim',
            self::Murottal => 'Murottal / Tilawah',
            self::Announcement => 'Pengumuman',
            self::SelfTest => 'Uji Speaker',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Adhan => 'primary',
            self::Iqamah => 'success',
            self::Tarhim => 'info',
            self::Murottal => 'teal',
            self::Announcement => 'warning',
            self::SelfTest => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Adhan => 'megaphone',
            self::Iqamah => 'bell',
            self::Tarhim => 'music-note-beamed',
            self::Murottal => 'book',
            self::Announcement => 'broadcast',
            self::SelfTest => 'soundwave',
        };
    }
}
