<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DutyStatus;
use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\FridayScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property DutyStatus $status
 */
class FridaySchedule extends Model
{
    /** @use HasFactory<FridayScheduleFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'date',
        'khatib_id',
        'imam_id',
        'muadzin_id',
        'external_khatib_name',
        'external_khatib_origin',
        'theme',
        'notes',
        'location',
        'start_time',
        'status',
        'created_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['date', 'khatib_id', 'imam_id', 'muadzin_id', 'theme', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => DutyStatus::class,
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function khatib(): BelongsTo
    {
        return $this->belongsTo(User::class, 'khatib_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function imam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imam_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function muadzin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muadzin_id');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<FridaySchedule>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('date', '>=', DateHelper::today()->toDateString())->orderBy('date');
    }

    /**
     * @param  Builder<FridaySchedule>  $query
     */
    public function scopeInMonth(Builder $query, CarbonImmutable|string $month): void
    {
        $carbon = DateHelper::toCarbon($month);

        $query->whereBetween('date', [
            $carbon->startOfMonth()->toDateString(),
            $carbon->endOfMonth()->toDateString(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * A khatib can be an employee on record or an outside speaker.
     */
    public function khatibName(): string
    {
        return $this->khatib?->name ?? $this->external_khatib_name ?? 'Belum ditentukan';
    }

    public function isComplete(): bool
    {
        return $this->khatibName() !== 'Belum ditentukan'
            && $this->imam_id !== null
            && $this->muadzin_id !== null;
    }

    /**
     * Roles still waiting to be filled, for the "belum lengkap" warning.
     *
     * @return array<int, string>
     */
    public function missingRoles(): array
    {
        $missing = [];

        if ($this->khatib_id === null && blank($this->external_khatib_name)) {
            $missing[] = 'Khatib';
        }

        if ($this->imam_id === null) {
            $missing[] = 'Imam';
        }

        if ($this->muadzin_id === null) {
            $missing[] = 'Muadzin';
        }

        return $missing;
    }
}
