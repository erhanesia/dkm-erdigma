<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationKind;
use App\Enums\NotificationStatus;
use App\Enums\PrayerName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp message the system intends to send through WOW.
 *
 * @property NotificationKind $kind
 * @property NotificationStatus $status
 * @property ?PrayerName $prayer
 * @property CarbonImmutable $reference_date
 */
class WhatsappNotification extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'kind',
        'reference_date',
        'prayer',
        'status',
        'message',
        'payload',
        'response',
        'error',
        'attempts',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'status' => NotificationStatus::class,
            'prayer' => PrayerName::class,
            'reference_date' => 'date',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<WhatsappNotification>  $query
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    /**
     * Attempts that ended badly and could reasonably be tried again.
     *
     * @param  Builder<WhatsappNotification>  $query
     */
    public function scopeRetryable(Builder $query, int $maxAttempts): void
    {
        $query->where('status', NotificationStatus::Failed)
            ->where('attempts', '<', $maxAttempts);
    }
}
