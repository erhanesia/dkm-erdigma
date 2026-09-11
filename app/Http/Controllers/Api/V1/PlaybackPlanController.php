<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\Device\PlaybackItemData;
use App\Http\Controllers\Api\V1\Concerns\ResolvesDevice;
use App\Http\Controllers\Controller;
use App\Services\Device\DeviceService;
use App\Services\Device\PlaybackPlanBuilder;
use App\Support\Helpers\ApiResponse;
use App\Support\Helpers\DateHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/devices/me/playback-plans`
 *
 * Hands the room's browser everything it must play today. The player caches the
 * response, so after a power cut it can resume from local storage instead of
 * waiting for anyone to restart a server.
 */
class PlaybackPlanController extends Controller
{
    use ResolvesDevice;

    public function __construct(
        private readonly PlaybackPlanBuilder $planBuilder,
        private readonly DeviceService $devices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $zone = $device->zone;

        $date = $request->filled('date')
            ? DateHelper::toCarbon($request->string('date')->toString())->startOfDay()
            : DateHelper::today();

        /*
         * An inactive zone still gets a valid response with an empty plan, so
         * the player stops on its own rather than replaying yesterday's cache.
         */
        $items = $zone->is_active
            ? $this->planBuilder->build($zone->load(['prayerSettings', 'murottalSchedules.track']), $date)
            : [];

        $this->devices->markPlanSynced($device);

        return ApiResponse::success(
            array_map(static fn (PlaybackItemData $item): array => $item->toArray(), $items),
            meta: [
                'date' => $date->toDateString(),
                'server_time' => DateHelper::now()->toIso8601String(),
                'zone_active' => $zone->is_active,
                'volume' => $device->volume,
                'min_audio_level' => (float) config('dkm.playback.min_audio_level'),
                'poll_interval' => (int) config('dkm.device.poll_interval'),
                'heartbeat_interval' => (int) config('dkm.device.heartbeat_interval'),
            ],
        );
    }
}
