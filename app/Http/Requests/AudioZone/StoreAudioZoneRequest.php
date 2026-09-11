<?php

declare(strict_types=1);

namespace App\Http\Requests\AudioZone;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAudioZoneRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('audio_zones', 'code')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_volume' => ['required', 'integer', 'between:0,100'],
            'is_adhan_enabled' => ['nullable', 'boolean'],
            'is_murottal_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::slug((string) $this->input('code', $this->input('name'))),
            'is_adhan_enabled' => $this->boolean('is_adhan_enabled'),
            'is_murottal_enabled' => $this->boolean('is_murottal_enabled'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode zona hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'code.unique' => 'Kode zona ini sudah dipakai.',
            'default_volume.between' => 'Volume harus di antara 0 dan 100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode zona',
            'name' => 'nama ruangan',
            'default_volume' => 'volume default',
        ];
    }
}
