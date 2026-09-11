<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Manual account creation — used for people the HRIS does not know about, such
 * as a visiting khatib. Accounts synced from the HRIS come in through
 * `hris:sync-users` instead and never touch this form.
 */
class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'external_employee_id' => [
                'nullable', 'string', 'max:30',
                Rule::unique('users', 'external_employee_id')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:24'],
            'gender' => ['nullable', 'string', 'max:20'],
            'position_name' => ['nullable', 'string', 'max:120'],
            'department_name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(UserRole::values())],
            'is_mentor' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'is_mentor' => $this->boolean('is_mentor'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function role(): UserRole
    {
        return UserRole::from((string) $this->validated('role'));
    }

    /**
     * Model attributes, with `role` stripped since it lives in a pivot table.
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return collect($this->validated())->except(['role', 'password_confirmation'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar.',
            'external_employee_id.unique' => 'NIK ini sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'external_employee_id' => 'NIK',
            'position_name' => 'jabatan',
            'department_name' => 'departemen',
            'role' => 'peran',
            'is_mentor' => 'status mentor',
        ];
    }
}
