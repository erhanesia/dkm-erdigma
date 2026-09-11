<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\Device\HeartbeatData;
use App\Http\Controllers\Api\V1\Concerns\ResolvesDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHeartbeatRequest;
use App\Services\Device\DeviceService;
use App\Support\Helpers\ApiResponse;
use App\Support\Helpers\DateHelper;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/v1/devices/me/heartbeats`
 *
 * The gap between heartbeats is what a power cut looks like in the data, and the
 * reported audio level is what tells the difference between "the browser is
 * running" and "sound is reaching the room".
 */
class DeviceHeartbeatController extends Controller
{
    use ResolvesDevice;

    public function __construct(
        private readonly DeviceService $devices,
    ) {}

    public function store(StoreHeartbeatRequest $request): JsonResponse
    {
        $device = $this->devices->recordHeartbeat(
            $this->device($request),
            HeartbeatData::fromRequest($request),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(
            [
                'status' => $device->resolveStatus()->value,
                'volume' => $device->volume,
            ],
            message: 'Denyut perangkat tercatat.',
            meta: ['server_time' => DateHelper::now()->toIso8601String()],
            status: Response::HTTP_CREATED,
        );
    }
}
