<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrayerName;
use App\Support\Concerns\RecordsActivity;
use Database\Factories\ZonePrayerSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PrayerName $prayer
 * @property bool $is_adhan_enabled
 */
class ZonePrayerSetting extends Model
{
    /** @use HasFactory<ZonePrayerSettingFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'audio_zone_id',
        'prayer',
        'is_adhan_enabled',
        'is_tarhim_enabled',
        'is_iqamah_enabled',
        'volume',
        'offset_minutes',
        'tarhim_lead_minutes',
        'iqamah_delay_minutes',
        'adhan_track_id',
        'tarhim_track_id',
        'iqamah_track_id',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['is_adhan_enabled', 'is_tarhim_enabled', 'is_iqamah_enabled', 'volume', 'offset_minutes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prayer' => PrayerName::class,
            'is_adhan_enabled' => 'boolean',
            'is_tarhim_enabled' => 'boolean',
            'is_iqamah_enabled' => 'boolean',
            'volume' => 'integer',
            'offset_minutes' => 'integer',
            'tarhim_lead_minutes' => 'integer',
            'iqamah_delay_minutes' => 'integer',
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
    public function adhanTrack(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'adhan_track_id');
    }

    /**
     * @return BelongsTo<AudioTrack, $this>
     */
    public function tarhimTrack(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'tarhim_track_id');
    }

    /**
     * @return BelongsTo<AudioTrack, $this>
     */
    public function iqamahTrack(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'iqamah_track_id');
    }
}
