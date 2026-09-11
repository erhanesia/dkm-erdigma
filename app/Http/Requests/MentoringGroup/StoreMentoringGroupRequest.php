<?php

declare(strict_types=1);

namespace App\Http\Requests\MentoringGroup;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A halaqah: one ustadz and the employees under their guidance.
 *
 * Capacity and single-membership rules are enforced in the service, because both
 * need to look at existing rows.
 */
class StoreMentoringGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('mentoring_groups', 'code')
                    ->ignore($this->route('mentoringGroup')?->id)
                    ->whereNull('deleted_at'),
            ],
            'mentor_id' => ['required', 'integer', 'exists:users,id'],
            'capacity' => ['required', 'integer', 'between:1,50'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_location' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * Group attributes, with the member list handled separately.
     *
     * @return array<string, mixed>
     */
    public function groupAttributes(): array
    {
        return collect($this->validated())->except('member_ids')->all();
    }

    /**
     * @return array<int, int>
     */
    public function memberIds(): array
    {
        return array_map('intval', (array) $this->validated('member_ids', []));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama halaqah',
            'code' => 'kode',
            'mentor_id' => 'mentor',
            'capacity' => 'kapasitas',
            'default_location' => 'lokasi rutin',
            'member_ids' => 'anggota',
        ];
    }
}
