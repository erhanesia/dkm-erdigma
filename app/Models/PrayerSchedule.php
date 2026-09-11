<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrayerName;
use App\Enums\ScheduleSource;
use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\PrayerScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property bool $is_manual_override
 */
class PrayerSchedule extends Model
{
    /** @use HasFactory<PrayerScheduleFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'date',
        'fajr',
        'sunrise',
        'dhuhr',
        'asr',
        'maghrib',
        'isha',
        'is_manual_override',
        'source',
        'calculation_method',
        'updated_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha', 'is_manual_override'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_manual_override' => 'boolean',
            'source' => ScheduleSource::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<PrayerSchedule>  $query
     */
    public function scopeForDate(Builder $query, CarbonImmutable|string $date): void
    {
        $query->whereDate('date', DateHelper::toCarbon($date)->toDateString());
    }

    /**
     * @param  Builder<PrayerSchedule>  $query
     */
    public function scopeBetween(Builder $query, CarbonImmutable|string $from, CarbonImmutable|string $to): void
    {
        $query->whereBetween('date', [
            DateHelper::toCarbon($from)->toDateString(),
            DateHelper::toCarbon($to)->toDateString(),
        ]);
    }

    /**
     * `HH:MM` for one prayer.
     */
    public function timeFor(PrayerName $prayer): string
    {
        return substr((string) $this->getAttribute($prayer->value), 0, 5);
    }

    /**
     * Full datetime for one prayer on this schedule's date.
     */
    public function momentFor(PrayerName $prayer): CarbonImmutable
    {
        return DateHelper::combine($this->date, (string) $this->getAttribute($prayer->value));
    }

    /**
     * Prayer name => `HH:MM`, in chronological order.
     *
     * @return array<string, string>
     */
    public function timings(): array
    {
        $timings = [];

        foreach (PrayerName::cases() as $prayer) {
            $timings[$prayer->value] = $this->timeFor($prayer);
        }

        return $timings;
    }

    /**
     * The next prayer strictly after the given moment, or null if the day is over.
     *
     * @return array{prayer: PrayerName, at: CarbonImmutable}|null
     */
    public function nextPrayerAfter(?CarbonImmutable $moment = null): ?array
    {
        $moment ??= DateHelper::now();

        foreach (PrayerName::withAdhan() as $prayer) {
            $at = $this->momentFor($prayer);

            if ($at->greaterThan($moment)) {
                return ['prayer' => $prayer, 'at' => $at];
            }
        }

        return null;
    }
}
