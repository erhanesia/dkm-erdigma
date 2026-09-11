<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AudioTrackType;
use App\Support\Concerns\RecordsActivity;
use Database\Factories\AudioTrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property AudioTrackType $type
 * @property string $file_path
 */
class AudioTrack extends Model
{
    /** @use HasFactory<AudioTrackFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title',
        'type',
        'reciter',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'duration_seconds',
        'is_default',
        'is_active',
        'uploaded_by',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['title', 'type', 'reciter', 'is_default', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AudioTrackType::class,
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<ZonePrayerSetting, $this>
     */
    public function zonePrayerSettingsAsAdhan(): HasMany
    {
        return $this->hasMany(ZonePrayerSetting::class, 'adhan_track_id');
    }

    /**
     * @return HasMany<ZonePrayerSetting, $this>
     */
    public function zonePrayerSettingsAsTarhim(): HasMany
    {
        return $this->hasMany(ZonePrayerSetting::class, 'tarhim_track_id');
    }

    /**
     * @return HasMany<ZonePrayerSetting, $this>
     */
    public function zonePrayerSettingsAsIqamah(): HasMany
    {
        return $this->hasMany(ZonePrayerSetting::class, 'iqamah_track_id');
    }

    /**
     * @return HasMany<MurottalSchedule, $this>
     */
    public function murottalSchedules(): HasMany
    {
        return $this->hasMany(MurottalSchedule::class);
    }

    /**
     * @param  Builder<AudioTrack>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<AudioTrack>  $query
     */
    public function scopeOfType(Builder $query, AudioTrackType|string $type): void
    {
        $query->where('type', $type instanceof AudioTrackType ? $type->value : $type);
    }
}
