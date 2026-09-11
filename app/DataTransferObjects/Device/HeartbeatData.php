<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Device;

use App\DataTransferObjects\BaseData;

/**
 * What the browser player reports about itself every 30 seconds.
 *
 * `audioLevel` is the measured output RMS. A player that is running but whose
 * level never leaves zero is the signature of "adzan tidak terdengar", and it is
 * only visible because the browser measures its own output.
 */
final readonly class HeartbeatData extends BaseData
{
    public function __construct(
        public bool $isAudioUnlocked,
        public ?float $audioLevel,
        public ?int $volume,
        public ?string $appVersion,
        public bool $isBrowserOnline = true,
    ) {}

    public static function fromArray(array $attributes): static
    {
        $level = $attributes['audio_level'] ?? null;

        return new self(
            isAudioUnlocked: self::boolean($attributes, 'is_audio_unlocked'),
            audioLevel: $level === null ? null : (float) $level,
            volume: self::nullableInt($attributes, 'volume'),
            appVersion: self::nullableString($attributes, 'app_version'),
            isBrowserOnline: self::boolean($attributes, 'is_browser_online', true),
        );
    }

    public function toArray(): array
    {
        return [
            'is_audio_unlocked' => $this->isAudioUnlocked,
            'audio_level' => $this->audioLevel,
            'volume' => $this->volume,
            'app_version' => $this->appVersion,
            'is_browser_online' => $this->isBrowserOnline,
        ];
    }
}
