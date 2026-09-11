<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user, for `GET /api/v1/me`.
 *
 * Grouped to mirror `UserMeResponse` in hris-api, which splits account fields
 * (id, email, phone, role) from employment fields (position, department, team).
 * The names are kept the same so anyone reading both APIs recognises them —
 * `external_employee_id`, `job_level`, `is_supervisor` and the rest.
 *
 * Nothing sensitive is included: no password hash, no remember token, no
 * `hris_synced_at` bookkeeping. Only what a client needs to greet the person and
 * decide what to show them.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,

            // Roles and permissions come from spatie/laravel-permission. A
            // client that hides a menu still cannot reach the route without
            // them — this is for the interface, not for authorisation.
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),

            // Flags the app itself acts on, kept at the top level because they
            // decide which dashboard a person sees.
            'is_mentor' => $this->is_mentor,
            'is_supervisor' => $this->is_supervisor,

            'employee' => [
                'external_employee_id' => $this->external_employee_id,
                'position_name' => $this->position_name,
                'job_level' => $this->job_level,
                'department_name' => $this->department_name,
                'team_name' => $this->team_name,
                'building_name' => $this->building_name,
                'gender' => $this->gender,
                'birth_date' => $this->birth_date?->toDateString(),
                'join_date' => $this->join_date?->toDateString(),
            ],

            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
