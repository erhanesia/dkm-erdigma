<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\AfterHoursSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One after-hours mentoring meeting.
 *
 * @property SessionStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Hidden(['qr_token'])]
class AfterHoursSession extends Model
{
    /** @use HasFactory<AfterHoursSessionFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'mentoring_group_id',
        'mentor_id',
        'topic',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'status',
        'qr_token',
        'is_qr_enabled',
        'is_public',
        'summary',
        'created_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['topic', 'starts_at', 'ends_at', 'location', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => SessionStatus::class,
            'is_qr_enabled' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsTo<MentoringGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MentoringGroup::class, 'mentoring_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<AfterHoursSession>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', DateHelper::now())->orderBy('starts_at');
    }

    /**
     * @param  Builder<AfterHoursSession>  $query
     */
    public function scopeBetween(Builder $query, CarbonImmutable|string $from, CarbonImmutable|string $to): void
    {
        $query->whereBetween('starts_at', [
            DateHelper::toCarbon($from)->startOfDay(),
            DateHelper::toCarbon($to)->endOfDay(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * QR check-in is only open around the session time, so a photographed QR
     * code cannot be reused a week later.
     */
    public function isCheckInOpen(?CarbonImmutable $moment = null): bool
    {
        if (! $this->is_qr_enabled || ! $this->status->acceptsAttendance()) {
            return false;
        }

        $moment ??= DateHelper::now();
        $opensAt = DateHelper::toCarbon($this->starts_at)->subMinutes((int) config('dkm.mentoring.check_in_opens_before'));
        $closesAt = DateHelper::toCarbon($this->ends_at)->addMinutes((int) config('dkm.mentoring.check_in_closes_after'));

        return $moment->betweenIncluded($opensAt, $closesAt);
    }

    public function isLateAt(?CarbonImmutable $moment = null): bool
    {
        return ($moment ?? DateHelper::now())->greaterThan(DateHelper::toCarbon($this->starts_at));
    }

    public function attendingCount(): int
    {
        return $this->attendances()->whereIn('status', AttendanceStatus::attendingValues())->count();
    }

    public function humanSchedule(): string
    {
        return DateHelper::formatLongDate($this->starts_at)
            .', '.DateHelper::formatTime($this->starts_at)
            .' – '.DateHelper::formatTime($this->ends_at);
    }
}
