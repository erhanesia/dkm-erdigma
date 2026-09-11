<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Lifecycle of one scheduled audio playback.
 *
 * `Silent` is the status that answers "adzan sering tidak terdengar": the player
 * did run the track, but the measured output level never rose above the audible
 * threshold, so the sound never actually reached the room.
 */
enum PlaybackStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Played = 'played';
    case Silent = 'silent';
    case Failed = 'failed';
    case Missed = 'missed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Played => 'Terputar',
            self::Silent => 'Tidak Terdengar',
            self::Failed => 'Gagal',
            self::Missed => 'Terlewat',
            self::Skipped => 'Dilewati',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Played => 'success',
            self::Silent => 'warning',
            self::Failed, self::Missed => 'danger',
            self::Skipped => 'light',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass-split',
            self::Played => 'check-circle',
            self::Silent => 'volume-mute',
            self::Failed => 'x-octagon',
            self::Missed => 'exclamation-triangle',
            self::Skipped => 'slash-circle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Dijadwalkan, belum waktunya diputar.',
            self::Played => 'Audio diputar dan suaranya terdeteksi keluar.',
            self::Silent => 'Audio diputar tetapi level suara nyaris nol — periksa amplifier, kabel, atau volume speaker.',
            self::Failed => 'Perangkat gagal memutar audio.',
            self::Missed => 'Tidak ada laporan dari perangkat sampai batas waktu — kemungkinan perangkat mati atau offline.',
            self::Skipped => 'Sengaja dilewati sesuai pengaturan zona.',
        };
    }

    /**
     * Statuses that require the DKM board to take action.
     *
     * @return array<int, self>
     */
    public static function problematic(): array
    {
        return [self::Silent, self::Failed, self::Missed];
    }

    /**
     * @return array<int, string>
     */
    public static function problematicValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::problematic());
    }

    public function isProblematic(): bool
    {
        return in_array($this, self::problematic(), true);
    }

    /**
     * Statuses a device is allowed to report back through the API.
     *
     * @return array<int, string>
     */
    public static function reportableValues(): array
    {
        return [self::Played->value, self::Silent->value, self::Failed->value, self::Skipped->value];
    }
}
