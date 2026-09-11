<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Pulls the device that `AuthenticateDevice` put on the request.
 *
 * Every device endpoint needs it, and none of them should ever read a device id
 * from the payload.
 */
trait ResolvesDevice
{
    protected function device(Request $request): Device
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        return $device;
    }
}
