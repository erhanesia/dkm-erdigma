<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Models\AudioZone;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Repositories\Contracts\DeviceCommandRepositoryInterface;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Collection;

/**
 * Remote control for rooms.
 *
 * Commands are queued and polled rather than pushed, which keeps the player
 * working through flaky office Wi-Fi and needs no websocket infrastructure.
 */
class DeviceCommandService
{
    /**
     * A command nobody picked up within this window is stale — replaying an
     * hour-old "play adhan" would be worse than dropping it.
     */
    private const DEFAULT_TTL_SECONDS = 300;

    public function __construct(
        private readonly DeviceCommandRepositoryInterface $commands,
        private readonly DeviceRepositoryInterface $devices,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatchTo(Device $device, DeviceCommandType $type, array $payload = [], ?User $issuer = null): DeviceCommand
    {
        return $this->commands->create([
            'device_id' => $device->id,
            'issued_by' => $issuer?->id,
            'type' => $type->value,
            'payload' => $payload === [] ? null : $payload,
            'status' => DeviceCommandStatus::Pending->value,
            'expires_at' => DateHelper::now()->addSeconds(self::DEFAULT_TTL_SECONDS),
        ]);
    }

    /**
     * Send the same instruction to every active player in a room.
     *
     * @param  array<string, mixed>  $payload
     * @return int Number of devices reached.
     */
    public function dispatchToZone(AudioZone $zone, DeviceCommandType $type, array $payload = [], ?User $issuer = null): int
    {
        $devices = $this->devices->activeInZone($zone->id);

        foreach ($devices as $device) {
            $this->dispatchTo($device, $type, $payload, $issuer);
        }

        return $devices->count();
    }

    /**
     * Hand the pending queue to a polling player and mark it delivered.
     *
     * @return Collection<int, DeviceCommand>
     */
    public function claimFor(Device $device): Collection
    {
        $pending = $this->commands->pendingFor($device);

        $this->commands->markDelivered($pending);

        return $pending;
    }

    public function acknowledge(Device $device, int $commandId, bool $succeeded, ?string $message = null): ?DeviceCommand
    {
        $command = $this->commands->find($commandId);

        if ($command === null || $command->device_id !== $device->id) {
            return null;
        }

        return $this->commands->acknowledge($command, $succeeded, $message);
    }

    /**
     * @return Collection<int, DeviceCommand>
     */
    public function historyFor(Device $device, int $limit = 10): Collection
    {
        return $this->commands->recentFor($device, $limit);
    }

    public function expireStale(): int
    {
        return $this->commands->expireStale();
    }
}
