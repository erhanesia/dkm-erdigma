<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Device;

use App\Enums\PlaybackType;
use App\Enums\PrayerName;
use Carbon\CarbonImmutable;

/**
 * One entry in a zone's daily playback plan.
 *
 * This is the contract between the server's planner and the browser player. The
 * player caches the whole plan in IndexedDB, so after a power cut it can resume
 * on its own without asking the server first — which is what removes the manual
 * restart the on-premise script used to need.
 */
final readonly class PlaybackItemData
{
    /**
     * @param  string  $referenceKey  Stable identifier, so replays are idempotent.
     */
    public function __construct(
        public string $referenceKey,
        public PlaybackType $type,
        public ?PrayerName $prayer,
        public CarbonImmutable $scheduledAt,
        public ?CarbonImmutable $endsAt,
        public ?int $trackId,
        public ?string $trackTitle,
        public ?string $streamUrl,
        public int $volume,
        public bool $isLoop = false,
        public bool $stopsBeforeAdhan = false,
    ) {}

    /**
     * Shape consumed by `resources/js/player/`.
     *
     * @return array{
     *     reference_key: string,
     *     type: string,
     *     type_label: string,
     *     prayer: string|null,
     *     prayer_label: string|null,
     *     scheduled_at: string,
     *     ends_at: string|null,
     *     track_id: int|null,
     *     track_title: string|null,
     *     stream_url: string|null,
     *     volume: int,
     *     is_loop: bool,
     *     stops_before_adhan: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'reference_key' => $this->referenceKey,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'prayer' => $this->prayer?->value,
            'prayer_label' => $this->prayer?->label(),
            'scheduled_at' => $this->scheduledAt->toIso8601String(),
            'ends_at' => $this->endsAt?->toIso8601String(),
            'track_id' => $this->trackId,
            'track_title' => $this->trackTitle,
            'stream_url' => $this->streamUrl,
            'volume' => $this->volume,
            'is_loop' => $this->isLoop,
            'stops_before_adhan' => $this->stopsBeforeAdhan,
        ];
    }
}
