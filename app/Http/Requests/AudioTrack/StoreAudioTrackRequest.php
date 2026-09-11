<?php

declare(strict_types=1);

namespace App\Http\Requests\AudioTrack;

use App\Enums\AudioTrackType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAudioTrackRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(AudioTrackType::values())],
            'reciter' => ['nullable', 'string', 'max:120'],
            'file' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', (array) config('dkm.audio.allowed_mimes')),
                'max:'.config('dkm.audio.max_size_kb'),
            ],
            'duration_seconds' => ['nullable', 'integer', 'between:1,7200'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Attributes for the model, with the upload itself handled separately.
     *
     * @return array<string, mixed>
     */
    public function trackAttributes(): array
    {
        return collect($this->validated())->except('file')->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas audio wajib diunggah.',
            'file.mimes' => 'Format audio harus salah satu dari: '
                .implode(', ', (array) config('dkm.audio.allowed_mimes')).'.',
            'file.max' => 'Ukuran berkas audio maksimal '
                .(int) (config('dkm.audio.max_size_kb') / 1024).' MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'type' => 'jenis audio',
            'reciter' => 'qari',
            'file' => 'berkas audio',
        ];
    }
}
