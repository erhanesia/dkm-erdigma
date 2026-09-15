<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Database\Factories\AfterHoursSessionSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A halaqah's standing appointment: one weekday and one time slot, every one
 * or two weeks, between two dates.
 *
 * Only the pattern lives here. Each meeting it produced is an ordinary
 * session, so everything built on sessions keeps working without knowing that
 * series exist.
 *
 * @property int $weekday ISO weekday: 1 is Monday, 7 is Sunday.
 * @property int $interval_weeks
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class AfterHoursSessionSeries extends Model
{
    /** @use HasFactory<AfterHoursSessionSeriesFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'mentoring_group_id',
        'topic',
        'weekday',
        'interval_weeks',
        'start_time',
        'end_time',
        'starts_on',
        'ends_on',
        'created_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['topic', 'weekday', 'interval_weeks', 'start_time', 'end_time', 'starts_on', 'ends_on'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'interval_weeks' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MentoringGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MentoringGroup::class, 'mentoring_group_id');
    }

    /**
     * @return HasMany<AfterHoursSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AfterHoursSession::class, 'series_id');
    }

    /**
     * `Setiap 2 minggu, hari Selasa`
     */
    public function describe(): string
    {
        $cadence = $this->interval_weeks === 1 ? 'Setiap minggu' : 'Setiap '.$this->interval_weeks.' minggu';

        return $cadence.', hari '.(DateHelper::weekdayOptions()[$this->weekday] ?? '');
    }
}
