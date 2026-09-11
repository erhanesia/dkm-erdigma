<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\DataTransferObjects\Device\HeartbeatData;
use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registration, pairing, and liveness of the browsers acting as room players.
 */
class DeviceService
{
    public function __construct(
        private readonly DeviceRepositoryInterface $devices,
    ) {}

    /**
     * Register a new player and return it alongside its one-time plain token.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{device: Device, token: string}
     */
    public function register(array $attributes): array
    {
        $token = TokenHelper::generateDeviceToken();

        $device = $this->devices->create([
            ...$attributes,
            'token_hash' => $token['hash'],
            'token_preview' => $token['preview'],
            'status' => DeviceStatus::NeverConnected->value,
        ]);

        return ['device' => $device, 'token' => $token['plain']];
    }

    /**
     * Issue a fresh secret; the previous one stops working immediately.
     */
    public function rotateToken(Device $device): string
    {
        $token = TokenHelper::generateDeviceToken();

        $this->devices->update($device, [
            'token_hash' => $token['hash'],
            'token_preview' => $token['preview'],
        ]);

        return $token['plain'];
    }

    public function authenticate(string $plainToken): ?Device
    {
        $device = $this->devices->findByToken($plainToken);

        if ($device === null || ! $device->is_active) {
            return null;
        }

        return $device;
    }

    /**
     * Persist a check-in from the player and refresh the device's cached state.
     */
    public function recordHeartbeat(Device $device, HeartbeatData $heartbeat, ?string $ipAddress = null, ?string $userAgent = null): Device
    {
        $now = DateHelper::now();

        DB::transaction(function () use ($device, $heartbeat, $ipAddress, $userAgent, $now): void {
            DeviceHeartbeat::query()->create([
                'device_id' => $device->id,
                'is_audio_unlocked' => $heartbeat->isAudioUnlocked,
                'audio_level' => $heartbeat->audioLevel,
                'volume' => $heartbeat->volume,
                'is_online_browser' => $heartbeat->isBrowserOnline,
                'app_version' => $heartbeat->appVersion,
                'reported_at' => $now,
            ]);

            $this->devices->touchHeartbeat($device, array_filter([
                'last_seen_at' => $now,
                'is_audio_unlocked' => $heartbeat->isAudioUnlocked,
                'last_audio_level' => $heartbeat->audioLevel,
                'volume' => $heartbeat->volume ?? $device->volume,
                'app_version' => $heartbeat->appVersion ?? $device->app_version,
                'last_ip_address' => $ipAddress ?? $device->last_ip_address,
                'user_agent' => $userAgent ?? $device->user_agent,
                'status' => $heartbeat->isAudioUnlocked ? DeviceStatus::Online->value : DeviceStatus::Muted->value,
            ], static fn (mixed $value): bool => $value !== null));
        });

        return $device->refresh();
    }

    public function markPlanSynced(Device $device): void
    {
        $this->devices->touchHeartbeat($device, ['plan_synced_at' => DateHelper::now()]);
    }

    /**
     * Reconcile stored status with reality — run by the scheduler so the
     * dashboard does not show a dead device as online.
     *
     * @return int Devices whose status changed.
     */
    public function refreshStatuses(): int
    {
        $changed = 0;

        foreach ($this->devices->all() as $device) {
            $resolved = $device->resolveStatus();

            if ($device->status !== $resolved) {
                $this->devices->touchHeartbeat($device, ['status' => $resolved->value]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @return Collection<int, Device>
     */
    public function offlineDevices(): Collection
    {
        return $this->devices->stale();
    }

    /**
     * @return array{total: int, online: int, offline: int}
     */
    public function healthSummary(): array
    {
        $total = $this->devices->countActive();
        $online = $this->devices->countOnline();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
        ];
    }
}
