<?php

declare(strict_types=1);

namespace App\Http\Requests\MurottalSchedule;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A tilawah window for one room.
 *
 * Overlap between windows in the same room is checked in the service, since it
 * needs a database look-up that validation rules cannot express cleanly.
 */
class StoreMurottalScheduleRequest extends FormRequest
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
            'audio_track_id' => ['nullable', 'integer', 'exists:audio_tracks,id'],
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'volume' => ['required', 'integer', 'between:0,100'],
            'is_loop' => ['nullable', 'boolean'],
            'stops_before_adhan' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_loop' => $this->boolean('is_loop'),
            'stops_before_adhan' => $this->boolean('stops_before_adhan'),
            'is_active' => $this->boolean('is_active'),
            'days_of_week' => array_map('intval', (array) $this->input('days_of_week', [])),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'days_of_week.required' => 'Pilih minimal satu hari.',
            'days_of_week.min' => 'Pilih minimal satu hari.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'audio_zone_id' => 'zona',
            'audio_track_id' => 'audio',
            'name' => 'nama jadwal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'days_of_week' => 'hari',
        ];
    }
}
