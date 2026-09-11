<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateDeviceCommandRequest;
use App\Http\Resources\DeviceCommandResource;
use App\Services\Device\DeviceCommandService;
use App\Support\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remote-control queue for one room's player.
 *
 * `GET`   /api/v1/devices/me/commands            — claim pending instructions
 * `PATCH` /api/v1/devices/me/commands/{command}  — report the outcome
 */
class DeviceCommandController extends Controller
{
    use ResolvesDevice;

    public function __construct(
        private readonly DeviceCommandService $commands,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $commands = $this->commands->claimFor($this->device($request));

        return ApiResponse::success(DeviceCommandResource::collection($commands));
    }

    public function update(UpdateDeviceCommandRequest $request, int $command): JsonResponse
    {
        $acknowledged = $this->commands->acknowledge(
            $this->device($request),
            $command,
            $request->boolean('succeeded'),
            $request->string('message')->toString() ?: null,
        );

        if ($acknowledged === null) {
            return ApiResponse::notFound('Perintah tidak ditemukan untuk perangkat ini.');
        }

        return ApiResponse::success(
            new DeviceCommandResource($acknowledged),
            message: 'Status perintah diperbarui.',
        );
    }
}
