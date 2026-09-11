<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Device\DeviceService;
use App\Support\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a room player by its bearer token.
 *
 * The resolved device is bound into the container so controllers can type-hint
 * it, and no route ever has to accept a device id from the client — a device can
 * only ever act as itself.
 */
class AuthenticateDevice
{
    public function __construct(
        private readonly DeviceService $devices,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Device-Token');

        if (blank($token)) {
            return ApiResponse::unauthorized('Token perangkat tidak disertakan.');
        }

        $device = $this->devices->authenticate($token);

        if ($device === null) {
            return ApiResponse::unauthorized('Token perangkat tidak valid atau perangkat dinonaktifkan.');
        }

        app()->instance('device', $device);
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
