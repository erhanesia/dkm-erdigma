<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Device;

use App\DataTransferObjects\BaseData;
use App\Enums\PlaybackStatus;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;

/**
 * The player's verdict on one playback it was asked to perform.
 */
final readonly class PlaybackReportData extends BaseData
{
    public function __construct(
        public string $referenceKey,
        public PlaybackStatus $status,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
        public ?float $peakAudioLevel,
        public ?float $averageAudioLevel,
        public ?int $volume,
        public ?string $failureReason,
    ) {}

    public static function fromArray(array $attributes): static
    {
        return new self(
            referenceKey: (string) $attributes['reference_key'],
            status: PlaybackStatus::from((string) $attributes['status']),
            startedAt: isset($attributes['started_at']) ? DateHelper::toCarbon($attributes['started_at']) : null,
            finishedAt: isset($attributes['finished_at']) ? DateHelper::toCarbon($attributes['finished_at']) : null,
            peakAudioLevel: isset($attributes['peak_audio_level']) ? (float) $attributes['peak_audio_level'] : null,
            averageAudioLevel: isset($attributes['average_audio_level']) ? (float) $attributes['average_audio_level'] : null,
            volume: self::nullableInt($attributes, 'volume'),
            failureReason: self::nullableString($attributes, 'failure_reason'),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'peak_audio_level' => $this->peakAudioLevel,
            'average_audio_level' => $this->averageAudioLevel,
            'volume' => $this->volume,
            'failure_reason' => $this->failureReason,
        ];
    }

    /**
     * The player says it played, but the measured level never crossed the
     * audible threshold — so the room heard nothing.
     */
    public function soundedSilent(float $threshold): bool
    {
        return $this->status === PlaybackStatus::Played
            && $this->peakAudioLevel !== null
            && $this->peakAudioLevel < $threshold;
    }
}
