<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Where a day's prayer times came from.
 *
 * `Official` matches the printed Kemenag schedule exactly. `Calculated` is the
 * local astronomical computation, which after calibration sits within about a
 * minute of it — close enough to pray by, but not the same claim, so the two are
 * never conflated.
 */
enum ScheduleSource: string implements HasLabel
{
    use EnumHelpers;

    case Official = 'official';
    case Calculated = 'calculated';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'Jadwal resmi Kemenag',
            self::Calculated => 'Perhitungan lokal',
            self::Manual => 'Diatur manual',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Official => 'Resmi',
            self::Calculated => 'Hitung',
            self::Manual => 'Manual',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Official => 'success',
            self::Calculated => 'secondary',
            self::Manual => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Official => 'patch-check',
            self::Calculated => 'calculator',
            self::Manual => 'pencil',
        };
    }
}
