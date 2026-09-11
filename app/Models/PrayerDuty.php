<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DutyStatus;
use App\Enums\PrayerName;
use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\PrayerDutyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PrayerName $prayer
 * @property DutyStatus $status
 */
class PrayerDuty extends Model
{
    /** @use HasFactory<PrayerDutyFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'date',
        'prayer',
        'muadzin_id',
        'imam_id',
        'status',
        'notes',
        'created_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['date', 'prayer', 'muadzin_id', 'imam_id', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'prayer' => PrayerName::class,
            'status' => DutyStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function muadzin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muadzin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function imam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imam_id');
    }

    /**
     * @param  Builder<PrayerDuty>  $query
     */
    public function scopeBetween(Builder $query, CarbonImmutable|string $from, CarbonImmutable|string $to): void
    {
        $query->whereBetween('date', [
            DateHelper::toCarbon($from)->toDateString(),
            DateHelper::toCarbon($to)->toDateString(),
        ]);
    }
}
