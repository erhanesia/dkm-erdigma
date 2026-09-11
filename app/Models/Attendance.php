<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AttendanceStatus $status
 * @property AttendanceMethod $method
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'after_hours_session_id',
        'user_id',
        'status',
        'method',
        'checked_in_at',
        'note',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'method' => AttendanceMethod::class,
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AfterHoursSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AfterHoursSession::class, 'after_hours_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    public function scopeAttending(Builder $query): void
    {
        $query->whereIn('status', AttendanceStatus::attendingValues());
    }
}
