<?php

declare(strict_types=1);

namespace App\Http\Requests\FridaySchedule;

use App\Enums\DutyStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFridayScheduleRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'khatib_id' => ['nullable', 'integer', 'exists:users,id'],
            'external_khatib_name' => ['nullable', 'string', 'max:120'],
            'external_khatib_origin' => ['nullable', 'string', 'max:150'],
            'imam_id' => ['nullable', 'integer', 'exists:users,id'],
            'muadzin_id' => ['nullable', 'integer', 'exists:users,id'],
            'theme' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:120'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(DutyStatus::values())],
        ];
    }

    /**
     * A khatib is either someone on the staff list or an outside speaker — but
     * filling in both would leave it ambiguous who is actually preaching.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasInternal = filled($this->input('khatib_id'));
            $hasExternal = filled($this->input('external_khatib_name'));

            if ($hasInternal && $hasExternal) {
                $validator->errors()->add(
                    'external_khatib_name',
                    'Pilih salah satu: khatib dari daftar karyawan atau khatib tamu, tidak keduanya.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'tanggal',
            'khatib_id' => 'khatib',
            'external_khatib_name' => 'nama khatib tamu',
            'external_khatib_origin' => 'asal khatib tamu',
            'imam_id' => 'imam',
            'muadzin_id' => 'muadzin',
            'theme' => 'tema khutbah',
            'location' => 'lokasi',
            'start_time' => 'waktu mulai',
        ];
    }
}
