<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum AttendanceMethod: string implements HasLabel
{
    use EnumHelpers;

    case QrCode = 'qr_code';
    case Manual = 'manual';
    case SelfReport = 'self_report';

    public function label(): string
    {
        return match ($this) {
            self::QrCode => 'Scan QR',
            self::Manual => 'Input Mentor',
            self::SelfReport => 'Lapor Mandiri',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::QrCode => 'primary',
            self::Manual => 'secondary',
            self::SelfReport => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::QrCode => 'qr-code-scan',
            self::Manual => 'pencil-square',
            self::SelfReport => 'person-check',
        };
    }
}
