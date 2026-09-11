<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel
{
    use EnumHelpers;

    case Present = 'present';
    case Late = 'late';
    case Excused = 'excused';
    case Sick = 'sick';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Excused => 'info',
            self::Sick => 'secondary',
            self::Absent => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Present => 'check-circle',
            self::Late => 'clock',
            self::Excused => 'envelope-paper',
            self::Sick => 'thermometer-half',
            self::Absent => 'x-circle',
        };
    }

    /**
     * Whether the entry counts toward the attendance rate.
     */
    public function countsAsAttending(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }

    /**
     * @return array<int, string>
     */
    public static function attendingValues(): array
    {
        return [self::Present->value, self::Late->value];
    }
}
