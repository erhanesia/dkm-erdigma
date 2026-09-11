<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrayerName;
use App\Support\Concerns\RecordsActivity;
use Database\Factories\AudioZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A room with speakers. Every audio decision is scoped to a zone, which is what
 * lets tilawah stay off in the CEO room while it runs everywhere else.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class AudioZone extends Model
{
    /** @use HasFactory<AudioZoneFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'code',
        'name',
        'floor',
        'description',
        'default_volume',
        'is_adhan_enabled',
        'is_murottal_enabled',
        'is_active',
        'sort_order',
    ];

    /** @var array<int, string> */
    protected array $auditable = [
        'code',
        'name',
        'default_volume',
        'is_adhan_enabled',
        'is_murottal_enabled',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_volume' => 'integer',
            'sort_order' => 'integer',
            'is_adhan_enabled' => 'boolean',
            'is_murottal_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<ZonePrayerSetting, $this>
     */
    public function prayerSettings(): HasMany
    {
        return $this->hasMany(ZonePrayerSetting::class);
    }

    /**
     * @return HasMany<MurottalSchedule, $this>
     */
    public function murottalSchedules(): HasMany
    {
        return $this->hasMany(MurottalSchedule::class);
    }

    /**
     * @return HasMany<PlaybackLog, $this>
     */
    public function playbackLogs(): HasMany
    {
        return $this->hasMany(PlaybackLog::class);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<AudioZone>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<AudioZone>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Prayer setting for a given prayer, if one has been configured.
     */
    public function settingFor(PrayerName $prayer): ?ZonePrayerSetting
    {
        return $this->prayerSettings->firstWhere('prayer', $prayer);
    }
}
