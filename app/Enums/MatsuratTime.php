<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Morning or evening.
 *
 * The two readings are nearly the same text; a handful of lines swap
 * "ashbahnaa" (we have entered the morning) for "amsainaa" (we have entered the
 * evening). Five of thirty-five entries differ in Sughra — which is exactly why
 * they are separate files rather than one list with a toggle.
 */
enum MatsuratTime: string implements HasLabel
{
    use EnumHelpers;

    case Pagi = 'pagi';
    case Petang = 'petang';

    public function label(): string
    {
        return match ($this) {
            self::Pagi => 'Pagi',
            self::Petang => 'Petang',
        };
    }

    /**
     * A Bootstrap Icons name — the set already bundled with the application, so
     * no glyph here is drawn by hand.
     *
     * Sun and moon rather than sunrise and sunset: the two dhikr are read
     * through the morning and through the evening, not at the two moments the
     * horizon icons depict, and at 14px the two horizon glyphs are nearly the
     * same shape anyway.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pagi => 'sun',
            self::Petang => 'moon-stars',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pagi => 'warning',
            self::Petang => 'indigo',
        };
    }
}
