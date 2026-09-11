<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\MurottalScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, int> $days_of_week
 * @property bool $is_active
 */
class MurottalSchedule extends Model
{
    /** @use HasFactory<MurottalScheduleFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'audio_zone_id',
        'audio_track_id',
        'name',
        'start_time',
        'end_time',
        'days_of_week',
        'volume',
        'is_loop',
        'stops_before_adhan',
        'is_active',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['name', 'start_time', 'end_time', 'days_of_week', 'volume', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'volume' => 'integer',
            'is_loop' => 'boolean',
            'stops_before_adhan' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AudioZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AudioZone::class, 'audio_zone_id');
    }

    /**
     * @return BelongsTo<AudioTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'audio_track_id');
    }

    /**
     * @param  Builder<MurottalSchedule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function runsOn(CarbonImmutable|string $date): bool
    {
        return in_array(DateHelper::toCarbon($date)->dayOfWeekIso, $this->days_of_week ?? [], true);
    }

    public function humanDays(): string
    {
        return DateHelper::formatWeekdays($this->days_of_week ?? []);
    }

    public function humanWindow(): string
    {
        return substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5);
    }
}
