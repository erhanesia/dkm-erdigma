<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDevice;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Support\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/devices/me`
 *
 * The player calls this on boot to learn which room it belongs to and how often
 * it should poll and report.
 */
class DeviceController extends Controller
{
    use ResolvesDevice;

    public function show(Request $request): JsonResponse
    {
        $device = $this->device($request)->load('zone');

        return ApiResponse::success(
            new DeviceResource($device),
            meta: [
                'server_time' => now()->toIso8601String(),
                'poll_interval' => (int) config('dkm.device.poll_interval'),
                'heartbeat_interval' => (int) config('dkm.device.heartbeat_interval'),
                'min_audio_level' => (float) config('dkm.playback.min_audio_level'),
            ],
        );
    }
}
