<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Support\Helpers\DateHelper;
use Database\Factories\DeviceCommandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property DeviceCommandType $type
 * @property DeviceCommandStatus $status
 * @property array<string, mixed>|null $payload
 */
class DeviceCommand extends Model
{
    /** @use HasFactory<DeviceCommandFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'device_id',
        'issued_by',
        'type',
        'payload',
        'status',
        'result_message',
        'delivered_at',
        'acknowledged_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceCommandType::class,
            'status' => DeviceCommandStatus::class,
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Commands the player still has to run: never delivered, or delivered but
     * not yet acknowledged, and not past their expiry.
     *
     * @param  Builder<DeviceCommand>  $query
     */
    public function scopeDeliverable(Builder $query): void
    {
        $query->whereIn('status', [DeviceCommandStatus::Pending, DeviceCommandStatus::Delivered])
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', DateHelper::now());
            });
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
