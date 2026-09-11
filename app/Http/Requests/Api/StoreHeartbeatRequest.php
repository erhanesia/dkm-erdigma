<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of `POST /api/v1/devices/me/heartbeats`.
 */
class StoreHeartbeatRequest extends FormRequest
{
    /**
     * The `device` middleware already proved the token; there is nothing further
     * to authorise because a device can only ever act as itself.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_audio_unlocked' => ['required', 'boolean'],
            // Normalised RMS of the player's own output, 0..1.
            'audio_level' => ['nullable', 'numeric', 'between:0,1'],
            'volume' => ['nullable', 'integer', 'between:0,100'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'is_browser_online' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio_level.between' => 'Level audio harus berada di antara 0 dan 1.',
            'volume.between' => 'Volume harus berada di antara 0 dan 100.',
        ];
    }
}
