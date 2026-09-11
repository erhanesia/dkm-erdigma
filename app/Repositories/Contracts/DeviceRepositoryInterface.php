<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Device>
 */
interface DeviceRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Device>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * Resolve a device from the raw token presented by the player.
     */
    public function findByToken(string $plainToken): ?Device;

    /**
     * @return Collection<int, Device>
     */
    public function activeInZone(int $zoneId): Collection;

    /**
     * @return Collection<int, Device>
     */
    public function allWithZone(): Collection;

    /**
     * Devices that have not checked in within the offline threshold.
     *
     * @return Collection<int, Device>
     */
    public function stale(): Collection;

    public function countOnline(): int;

    public function countActive(): int;

    /**
     * Record a check-in from the player.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function touchHeartbeat(Device $device, array $attributes): void;
}
