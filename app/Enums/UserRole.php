<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    use EnumHelpers;

    case SuperAdmin = 'super_admin';
    case DkmAdmin = 'dkm_admin';
    case Mentor = 'mentor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::DkmAdmin => 'Pengurus DKM',
            self::Mentor => 'Mentor (Ustadz)',
            self::Employee => 'Karyawan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'dark',
            self::DkmAdmin => 'primary',
            self::Mentor => 'info',
            self::Employee => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'shield-lock',
            self::DkmAdmin => 'person-badge',
            self::Mentor => 'mortarboard',
            self::Employee => 'person',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Akses penuh termasuk pengaturan sistem dan manajemen pengguna.',
            self::DkmAdmin => 'Mengelola jadwal sholat, audio, jadwal Jum\'at, dan kegiatan after hours.',
            self::Mentor => 'Mengelola halaqah binaan dan mencatat kehadiran anggotanya.',
            self::Employee => 'Melihat jadwal dan melakukan presensi mandiri.',
        };
    }

    /**
     * Roles that may access the administrative back office.
     *
     * @return array<int, string>
     */
    public static function administrative(): array
    {
        return [self::SuperAdmin->value, self::DkmAdmin->value];
    }
}
