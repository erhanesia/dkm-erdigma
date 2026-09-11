<?php

declare(strict_types=1);

namespace App\Http\Requests\AudioZone;

use App\Enums\PrayerName;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The per-prayer adhan grid for one room — the form that lets the CEO room keep
 * a different configuration from the lobby.
 */
class UpdateZonePrayerSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            /*
             * Only the five prayers may appear as keys, so a crafted payload
             * cannot create settings rows for anything else.
             */
            'settings.*' => ['array'],
            'settings.*.is_adhan_enabled' => ['nullable', 'boolean'],
            'settings.*.is_tarhim_enabled' => ['nullable', 'boolean'],
            'settings.*.is_iqamah_enabled' => ['nullable', 'boolean'],
            'settings.*.volume' => ['required', 'integer', 'between:0,100'],
            'settings.*.offset_minutes' => ['required', 'integer', 'between:-30,30'],
            'settings.*.tarhim_lead_minutes' => ['required', 'integer', 'between:1,60'],
            'settings.*.iqamah_delay_minutes' => ['required', 'integer', 'between:1,60'],
            'settings.*.adhan_track_id' => ['nullable', 'integer', 'exists:audio_tracks,id'],
            'settings.*.tarhim_track_id' => ['nullable', 'integer', 'exists:audio_tracks,id'],
            'settings.*.iqamah_track_id' => ['nullable', 'integer', 'exists:audio_tracks,id'],
        ];
    }

    /**
     * Keys outside the five prayers are rejected rather than silently ignored.
     */
    public function withValidator(Validator $validator): void
    {
        $allowed = array_map(
            static fn (PrayerName $prayer): string => $prayer->value,
            PrayerName::withAdhan(),
        );

        $validator->after(function (Validator $validator) use ($allowed): void {
            foreach (array_keys((array) $this->input('settings', [])) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $validator->errors()->add('settings', 'Terdapat waktu sholat yang tidak dikenali: '.$key.'.');
                }
            }
        });
    }

    /**
     * Normalised grid keyed by prayer, ready for the repository.
     *
     * @return array<string, array<string, mixed>>
     */
    public function settingsByPrayer(): array
    {
        /** @var array<string, array<string, mixed>> $raw */
        $raw = $this->validated('settings');
        $normalised = [];

        foreach ($raw as $prayer => $attributes) {
            $normalised[$prayer] = [
                ...$attributes,
                'is_adhan_enabled' => (bool) ($attributes['is_adhan_enabled'] ?? false),
                'is_tarhim_enabled' => (bool) ($attributes['is_tarhim_enabled'] ?? false),
                'is_iqamah_enabled' => (bool) ($attributes['is_iqamah_enabled'] ?? false),
            ];
        }

        return $normalised;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.*.volume.between' => 'Volume harus di antara 0 dan 100.',
            'settings.*.offset_minutes.between' => 'Selisih waktu hanya boleh antara -30 sampai 30 menit.',
        ];
    }
}
