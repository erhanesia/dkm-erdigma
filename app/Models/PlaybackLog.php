<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaybackStatus;
use App\Enums\PlaybackType;
use App\Enums\PrayerName;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\PlaybackLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PlaybackType $type
 * @property PlaybackStatus $status
 * @property float|null $peak_audio_level
 * @property Carbon $scheduled_at
 */
class PlaybackLog extends Model
{
    /** @use HasFactory<PlaybackLogFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'audio_zone_id',
        'device_id',
        'audio_track_id',
        'type',
        'prayer',
        'reference_key',
        'scheduled_at',
        'started_at',
        'finished_at',
        'status',
        'peak_audio_level',
        'average_audio_level',
        'volume',
        'failure_reason',
        'is_acknowledged',
        'acknowledged_by',
        'acknowledged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlaybackType::class,
            'status' => PlaybackStatus::class,
            'prayer' => PrayerName::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'peak_audio_level' => 'float',
            'average_audio_level' => 'float',
            'volume' => 'integer',
            'is_acknowledged' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsTo<AudioZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AudioZone::class, 'audio_zone_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<AudioTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'audio_track_id');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<PlaybackLog>  $query
     */
    public function scopeProblematic(Builder $query): void
    {
        $query->whereIn('status', PlaybackStatus::problematicValues());
    }

    /**
     * @param  Builder<PlaybackLog>  $query
     */
    public function scopeUnacknowledged(Builder $query): void
    {
        $query->where('is_acknowledged', false);
    }

    /**
     * @param  Builder<PlaybackLog>  $query
     */
    public function scopeOnDate(Builder $query, CarbonImmutable|string $date): void
    {
        $carbon = DateHelper::toCarbon($date);

        $query->whereBetween('scheduled_at', [$carbon->startOfDay(), $carbon->endOfDay()]);
    }

    /**
     * @param  Builder<PlaybackLog>  $query
     */
    public function scopeSince(Builder $query, CarbonImmutable|string $from): void
    {
        $query->where('scheduled_at', '>=', DateHelper::toCarbon($from));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Explains, in plain Indonesian, what the DKM board should check next.
     */
    public function diagnosis(): string
    {
        return match ($this->status) {
            PlaybackStatus::Silent => 'Browser memutar audio tetapi level suara nyaris nol. Periksa amplifier, kabel speaker, dan volume perangkat di ruangan ini.',
            PlaybackStatus::Missed => 'Perangkat tidak melaporkan apa pun. Pastikan PC/tablet menyala dan halaman player terbuka.',
            PlaybackStatus::Failed => $this->failure_reason ?: 'Perangkat melaporkan kegagalan saat memutar audio.',
            default => $this->status->description(),
        };
    }
}
