<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceHeartbeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $device_id
 * @property Carbon $reported_at
 */
class DeviceHeartbeat extends Model
{
    /** @use HasFactory<DeviceHeartbeatFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'device_id',
        'is_audio_unlocked',
        'audio_level',
        'volume',
        'is_online_browser',
        'app_version',
        'reported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_audio_unlocked' => 'boolean',
            'is_online_browser' => 'boolean',
            'audio_level' => 'float',
            'volume' => 'integer',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
