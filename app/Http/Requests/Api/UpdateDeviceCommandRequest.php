<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of `PATCH /api/v1/devices/me/commands/{command}` — the player telling
 * the server whether it managed to carry out an instruction.
 */
class UpdateDeviceCommandRequest extends FormRequest
{
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
            'succeeded' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
