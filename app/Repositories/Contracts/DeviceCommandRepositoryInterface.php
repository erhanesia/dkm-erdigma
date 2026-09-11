<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<DeviceCommand>
 */
interface DeviceCommandRepositoryInterface extends RepositoryInterface
{
    /**
     * Commands the given device still has to execute.
     *
     * @return Collection<int, DeviceCommand>
     */
    public function pendingFor(Device $device): Collection;

    /**
     * @param  Collection<int, DeviceCommand>  $commands
     */
    public function markDelivered(Collection $commands): void;

    public function acknowledge(DeviceCommand $command, bool $succeeded, ?string $message = null): DeviceCommand;

    /**
     * @return Collection<int, DeviceCommand>
     */
    public function recentFor(Device $device, int $limit = 10): Collection;

    public function expireStale(): int;
}
