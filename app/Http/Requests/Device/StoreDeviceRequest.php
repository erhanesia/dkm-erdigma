<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(UserRole::administrative()) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'audio_zone_id' => ['required', 'integer', 'exists:audio_zones,id'],
            'name' => ['required', 'string', 'max:120'],
            'volume' => ['required', 'integer', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'audio_zone_id' => 'zona',
            'name' => 'nama perangkat',
            'volume' => 'volume',
            'notes' => 'catatan',
        ];
    }
}
