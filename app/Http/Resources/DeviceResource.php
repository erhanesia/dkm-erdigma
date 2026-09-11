<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'volume' => $this->volume,
            'status' => $this->resolveStatus()->value,
            'is_active' => $this->is_active,
            'zone' => [
                'id' => $this->zone->id,
                'code' => $this->zone->code,
                'name' => $this->zone->name,
                'default_volume' => $this->zone->default_volume,
                'is_adhan_enabled' => $this->zone->is_adhan_enabled,
                'is_murottal_enabled' => $this->zone->is_murottal_enabled,
                'is_active' => $this->zone->is_active,
            ],
        ];
    }
}
