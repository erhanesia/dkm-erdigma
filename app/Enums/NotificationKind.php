<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * What a WhatsApp announcement is about.
 *
 * Friday is its own kind rather than a flag on the prayer reminder, because the
 * message carries different information — the khatib, imam and muezzin — and is
 * composed from a different source.
 */
enum NotificationKind: string implements HasLabel
{
    use EnumHelpers;

    case PrayerReminder = 'prayer_reminder';
    case FridayReminder = 'friday_reminder';

    public function label(): string
    {
        return match ($this) {
            self::PrayerReminder => 'Pengingat Sholat',
            self::FridayReminder => 'Pengingat Sholat Jumat',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PrayerReminder => 'alarm',
            self::FridayReminder => 'person-video3',
        };
    }
}
