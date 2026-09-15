<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Concerns\EnumHelpers;
use App\Support\Contracts\HasLabel;

enum SessionStatus: string implements HasLabel
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Ongoing => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'primary',
            self::Ongoing => 'success',
            self::Completed => 'secondary',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Scheduled => 'calendar-event',
            self::Ongoing => 'broadcast-pin',
            self::Completed => 'check2-all',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * Attendance may only be recorded while a session is open.
     */
    public function acceptsAttendance(): bool
    {
        return in_array($this, [self::Scheduled, self::Ongoing], true);
    }

    /**
     * Whether every detail of the session can still change.
     *
     * A completed session keeps only its status and last reading open — see
     * `AfterHoursSessionService::EDITABLE_ONCE_COMPLETED`.
     */
    public function isEditable(): bool
    {
        return $this !== self::Completed;
    }
}
