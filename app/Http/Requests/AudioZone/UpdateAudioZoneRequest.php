<?php

declare(strict_types=1);

namespace App\Http\Requests\AudioZone;

use Illuminate\Validation\Rule;

/**
 * Same shape as creating a zone, except the uniqueness check skips the row being
 * edited.
 */
class UpdateAudioZoneRequest extends StoreAudioZoneRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['code'] = [
            'required', 'string', 'max:40', 'regex:/^[a-z0-9\-]+$/',
            Rule::unique('audio_zones', 'code')
                ->ignore($this->route('audioZone')?->id)
                ->whereNull('deleted_at'),
        ];

        return $rules;
    }
}
