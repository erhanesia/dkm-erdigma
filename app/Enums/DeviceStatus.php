<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum DeviceStatus: string implements HasLabel
{
    use EnumHelpers;

    case Online = 'online';
    case Offline = 'offline';
    case Muted = 'muted';
    case NeverConnected = 'never_connected';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Muted => 'Audio Terkunci',
            self::NeverConnected => 'Belum Pernah Terhubung',
            self::Disabled => 'Dinonaktifkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online => 'success',
            self::Offline => 'danger',
            self::Muted => 'warning',
            self::NeverConnected => 'secondary',
            self::Disabled => 'light',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Online => 'wifi',
            self::Offline => 'wifi-off',
            self::Muted => 'volume-mute',
            self::NeverConnected => 'question-circle',
            self::Disabled => 'power',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Online => 'Perangkat aktif dan mengirim laporan secara berkala.',
            self::Offline => 'Tidak ada kabar dari perangkat. Pastikan PC/tablet menyala dan halaman player terbuka.',
            self::Muted => 'Halaman player terbuka tetapi audio browser belum diizinkan. Klik "Aktifkan Audio" di perangkat.',
            self::NeverConnected => 'Perangkat sudah didaftarkan tetapi halaman player belum pernah dibuka.',
            self::Disabled => 'Perangkat sengaja dimatikan oleh pengurus.',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Online;
    }
}
