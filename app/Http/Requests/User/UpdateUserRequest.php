<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Editing an existing account.
 *
 * The password becomes optional, and fields the HRIS owns are locked once the
 * row is synced — editing them here would only survive until the next sync.
 */
class UpdateUserRequest extends StoreUserRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $rules = parent::rules();

        $rules['email'] = [
            'required', 'string', 'email', 'max:255',
            Rule::unique('users', 'email')->ignore($user?->id)->whereNull('deleted_at'),
        ];

        $rules['external_employee_id'] = [
            'nullable', 'string', 'max:30',
            Rule::unique('users', 'external_employee_id')->ignore($user?->id)->whereNull('deleted_at'),
        ];

        $rules['password'] = ['nullable', 'confirmed', Password::defaults()];

        if ($user?->isManagedByHris() === true) {
            foreach (['name', 'external_employee_id', 'position_name', 'department_name', 'gender'] as $field) {
                $rules[$field] = ['nullable', 'prohibited'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'name.prohibited' => 'Data ini dikelola oleh sistem HRIS dan tidak bisa diubah dari sini.',
            'external_employee_id.prohibited' => 'NIK dikelola oleh sistem HRIS.',
            'position_name.prohibited' => 'Jabatan dikelola oleh sistem HRIS.',
            'department_name.prohibited' => 'Departemen dikelola oleh sistem HRIS.',
            'gender.prohibited' => 'Jenis kelamin dikelola oleh sistem HRIS.',
        ];
    }
}
