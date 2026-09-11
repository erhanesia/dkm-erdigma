<?php

declare(strict_types=1);

namespace App\Support\Helpers;

/**
 * Number and byte formatting used by dashboards, reports, and audio listings.
 */
final class NumberHelper
{
    /**
     * `1.234` (Indonesian thousands separator).
     */
    public static function format(int|float $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    /**
     * `87,5%` — guards against dividing by zero.
     */
    public static function percentage(int|float $part, int|float $total, int $decimals = 1): string
    {
        if ((float) $total === 0.0) {
            return '0%';
        }

        return self::format($part / $total * 100, $decimals).'%';
    }

    public static function percentageValue(int|float $part, int|float $total, int $decimals = 1): float
    {
        if ((float) $total === 0.0) {
            return 0.0;
        }

        return round($part / $total * 100, $decimals);
    }

    /**
     * `4,2 MB`
     */
    public static function fileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return self::format($size, $index === 0 ? 0 : 1).' '.$units[$index];
    }

    /**
     * `03:24` for an audio duration given in seconds.
     */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '--:--';
        }

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
