<?php

declare(strict_types=1);

namespace App\Http\Requests\Session;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\MentoringGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One after-hours mentoring meeting.
 */
class StoreSessionRequest extends FormRequest
{
    /**
     * Administrators may schedule for any halaqah; a mentor only for their own.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(UserRole::administrative())) {
            return true;
        }

        $groupId = (int) $this->input('mentoring_group_id');

        return MentoringGroup::query()
            ->whereKey($groupId)
            ->where('mentor_id', $user->id)
            ->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mentoring_group_id' => ['required', 'integer', 'exists:mentoring_groups,id'],
            'topic' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(SessionStatus::values())],
            'is_qr_enabled' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_qr_enabled' => $this->boolean('is_qr_enabled'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mentoring_group_id' => 'halaqah',
            'topic' => 'materi kegiatan',
            'starts_at' => 'waktu mulai',
            'ends_at' => 'waktu selesai',
            'location' => 'tempat',
            'is_public' => 'tampilkan di halaman publik',
        ];
    }
}
