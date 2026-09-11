<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum DeviceCommandStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Delivered = 'delivered';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Delivered => 'Terkirim',
            self::Acknowledged => 'Dijalankan',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Delivered => 'info',
            self::Acknowledged => 'success',
            self::Failed => 'danger',
            self::Expired => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass-split',
            self::Delivered => 'send',
            self::Acknowledged => 'check-circle',
            self::Failed => 'x-octagon',
            self::Expired => 'clock-history',
        };
    }
}
