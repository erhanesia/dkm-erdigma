<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

/**
 * Confirmation state of an assigned duty — Friday sermon roles and the daily
 * muezzin roster both use this so nobody finds out at the last minute that the
 * khatib is not coming.
 */
enum DutyStatus: string implements HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Assigned = 'assigned';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Replaced = 'replaced';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Assigned => 'Ditugaskan',
            self::Confirmed => 'Dikonfirmasi',
            self::Declined => 'Berhalangan',
            self::Replaced => 'Digantikan',
            self::Done => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::Assigned => 'primary',
            self::Confirmed => 'success',
            self::Declined => 'danger',
            self::Replaced => 'warning',
            self::Done => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'file-earmark',
            self::Assigned => 'person-plus',
            self::Confirmed => 'patch-check',
            self::Declined => 'person-dash',
            self::Replaced => 'arrow-left-right',
            self::Done => 'check2-all',
        };
    }

    public function needsFollowUp(): bool
    {
        return in_array($this, [self::Draft, self::Assigned, self::Declined], true);
    }
}
