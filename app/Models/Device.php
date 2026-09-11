<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Support\Concerns\RecordsActivity;
use App\Support\Helpers\DateHelper;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A browser left open on the player page in a room.
 *
 * @property int $id
 * @property DeviceStatus $status
 * @property Carbon|null $last_seen_at
 */
#[Hidden(['token_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'audio_zone_id',
        'name',
        'token_hash',
        'token_preview',
        'status',
        'volume',
        'is_audio_unlocked',
        'last_audio_level',
        'app_version',
        'user_agent',
        'last_ip_address',
        'last_seen_at',
        'plan_synced_at',
        'is_active',
        'notes',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['name', 'audio_zone_id', 'volume', 'is_active', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'volume' => 'integer',
            'is_audio_unlocked' => 'boolean',
            'is_active' => 'boolean',
            'last_audio_level' => 'float',
            'last_seen_at' => 'datetime',
            'plan_synced_at' => 'datetime',
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
     * @return HasMany<DeviceHeartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    /**
     * @return HasMany<DeviceCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
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
     * @param  Builder<Device>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Devices that have gone quiet for longer than the configured threshold.
     *
     * @param  Builder<Device>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $threshold = DateHelper::now()->subSeconds((int) config('dkm.device.offline_threshold'));

        $query->where('is_active', true)
            ->where(function (Builder $builder) use ($threshold): void {
                $builder->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold);
            });
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Status derived from the last heartbeat rather than the stored column, so
     * the UI never shows a device as online just because nobody ran the cron.
     */
    public function resolveStatus(): DeviceStatus
    {
        if (! $this->is_active) {
            return DeviceStatus::Disabled;
        }

        if ($this->last_seen_at === null) {
            return DeviceStatus::NeverConnected;
        }

        $threshold = (int) config('dkm.device.offline_threshold');

        if ($this->last_seen_at->diffInSeconds(DateHelper::now()) > $threshold) {
            return DeviceStatus::Offline;
        }

        return $this->is_audio_unlocked ? DeviceStatus::Online : DeviceStatus::Muted;
    }

    public function isOnline(): bool
    {
        return $this->resolveStatus()->isHealthy();
    }
}
