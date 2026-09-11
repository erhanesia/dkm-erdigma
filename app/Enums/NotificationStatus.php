<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Where a WhatsApp announcement got to.
 *
 * `Skipped` is not a failure: it records that the announcer decided not to send
 * — the feature was switched off, or the prayer had no schedule — so a gap in
 * the group chat has an explanation rather than being a silence nobody can
 * account for.
 */
enum NotificationStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass-split',
            self::Sent => 'check-circle',
            self::Failed => 'x-circle',
            self::Skipped => 'dash-circle',
        };
    }
}
