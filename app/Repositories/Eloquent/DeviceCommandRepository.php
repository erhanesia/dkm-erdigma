<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\DeviceCommandStatus;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Repositories\Contracts\DeviceCommandRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<DeviceCommand>
 */
class DeviceCommandRepository extends BaseRepository implements DeviceCommandRepositoryInterface
{
    protected function model(): string
    {
        return DeviceCommand::class;
    }

    public function pendingFor(Device $device): Collection
    {
        return $this->query()
            ->where('device_id', $device->id)
            ->deliverable()
            ->orderBy('id')
            ->get();
    }

    public function markDelivered(Collection $commands): void
    {
        $ids = $commands->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $this->query()
            ->whereIn('id', $ids)
            ->where('status', DeviceCommandStatus::Pending)
            ->update([
                'status' => DeviceCommandStatus::Delivered->value,
                'delivered_at' => DateHelper::now(),
            ]);
    }

    public function acknowledge(DeviceCommand $command, bool $succeeded, ?string $message = null): DeviceCommand
    {
        $command->forceFill([
            'status' => $succeeded ? DeviceCommandStatus::Acknowledged : DeviceCommandStatus::Failed,
            'result_message' => $message,
            'acknowledged_at' => DateHelper::now(),
        ])->save();

        return $command;
    }

    public function recentFor(Device $device, int $limit = 10): Collection
    {
        return $this->query()
            ->with('issuer')
            ->where('device_id', $device->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function expireStale(): int
    {
        return $this->query()
            ->whereIn('status', [DeviceCommandStatus::Pending, DeviceCommandStatus::Delivered])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', DateHelper::now())
            ->update(['status' => DeviceCommandStatus::Expired->value]);
    }

    /**
     * @param  Builder<DeviceCommand>  $query
     * @return Builder<DeviceCommand>
     */
    protected function applyDefaultOrder(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }
}
