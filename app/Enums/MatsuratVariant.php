<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Which recension of Al-Ma'tsurat.
 *
 * Sughra is the short one Hasan al-Banna intended for daily use; Kubra is the
 * fuller collection. Both are in the data, and neither is a subset of the other
 * in file order, so they are kept as separate readings rather than one list with
 * items hidden.
 */
enum MatsuratVariant: string implements HasLabel
{
    use EnumHelpers;

    case Sugro = 'sugro';
    case Kubro = 'kubro';

    public function label(): string
    {
        return match ($this) {
            self::Sugro => 'Sughra',
            self::Kubro => 'Kubra',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sugro => 'Ringkas — untuk dibaca setiap hari',
            self::Kubro => 'Lengkap — lebih panjang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sugro => 'primary',
            self::Kubro => 'teal',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sugro => 'bookmark',
            self::Kubro => 'bookmarks',
        };
    }
}
