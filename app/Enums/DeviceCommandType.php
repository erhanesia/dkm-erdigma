<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Remote instructions queued for a player. The player polls for pending commands
 * so the DKM board can control any room from the office — no need to walk over
 * to the CEO room to turn tilawah back on.
 */
enum DeviceCommandType: string implements HasLabel
{
    use EnumHelpers;

    case PlayTrack = 'play_track';
    case Stop = 'stop';
    case SetVolume = 'set_volume';
    case SelfTest = 'self_test';
    case ReloadPlan = 'reload_plan';
    case Refresh = 'refresh';

    public function label(): string
    {
        return match ($this) {
            self::PlayTrack => 'Putar Audio',
            self::Stop => 'Hentikan Audio',
            self::SetVolume => 'Ubah Volume',
            self::SelfTest => 'Uji Speaker',
            self::ReloadPlan => 'Muat Ulang Jadwal',
            self::Refresh => 'Muat Ulang Halaman',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PlayTrack => 'primary',
            self::Stop => 'danger',
            self::SetVolume => 'info',
            self::SelfTest => 'warning',
            self::ReloadPlan, self::Refresh => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PlayTrack => 'play-circle',
            self::Stop => 'stop-circle',
            self::SetVolume => 'volume-up',
            self::SelfTest => 'soundwave',
            self::ReloadPlan => 'arrow-repeat',
            self::Refresh => 'arrow-clockwise',
        };
    }

    /**
     * Validation rules for the `payload` column, keyed by command type.
     *
     * @return array<string, string>
     */
    public function payloadRules(): array
    {
        return match ($this) {
            self::PlayTrack => [
                'payload.audio_track_id' => ['required', 'integer', 'exists:audio_tracks,id'],
                'payload.volume' => ['nullable', 'integer', 'between:0,100'],
            ],
            self::SetVolume => [
                'payload.volume' => ['required', 'integer', 'between:0,100'],
            ],
            default => [],
        };
    }
}
