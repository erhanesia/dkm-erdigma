<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Support\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person in the DKM app.
 *
 * Most rows are a mirror of the HRIS directory, keyed by `hris_employee_id`.
 * Rows created locally — an outside khatib, for instance — leave the HRIS keys
 * null. `is_mentor` is owned by DKM alone: the HRIS has no concept of halaqah.
 *
 * @property bool $is_mentor
 * @property bool $is_active
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'hris_user_id',
        'hris_employee_id',
        'external_employee_id',
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'birth_date',
        'join_date',
        'position_name',
        'job_level',
        'department_name',
        'team_name',
        'building_name',
        'is_supervisor',
        'avatar_url',
        'is_mentor',
        'is_active',
        'hris_synced_at',
    ];

    /** @var array<int, string> */
    protected array $auditable = [
        'external_employee_id',
        'name',
        'email',
        'phone',
        'department_name',
        'position_name',
        'is_mentor',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'join_date' => 'date',
            'hris_synced_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_supervisor' => 'boolean',
            'is_mentor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * Halaqah this user leads as a mentor (ustadz).
     *
     * @return HasMany<MentoringGroup, $this>
     */
    public function mentoredGroups(): HasMany
    {
        return $this->hasMany(MentoringGroup::class, 'mentor_id');
    }

    /**
     * Halaqah memberships this user holds as an employee.
     *
     * @return HasMany<MentoringGroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(MentoringGroupMember::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<FridaySchedule, $this>
     */
    public function khatibSchedules(): HasMany
    {
        return $this->hasMany(FridaySchedule::class, 'khatib_id');
    }

    /**
     * @return HasMany<PrayerDuty, $this>
     */
    public function muadzinDuties(): HasMany
    {
        return $this->hasMany(PrayerDuty::class, 'muadzin_id');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeMentors(Builder $query): void
    {
        $query->where('is_mentor', true);
    }

    /**
     * Rows that came from the HRIS, as opposed to ones created here.
     *
     * @param  Builder<User>  $query
     */
    public function scopeFromHris(Builder $query): void
    {
        $query->whereNotNull('hris_employee_id');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeLocalOnly(Builder $query): void
    {
        $query->whereNull('hris_employee_id');
    }

    // ---------------------------------------------------------------------
    // Accessors & helpers
    // ---------------------------------------------------------------------

    /**
     * `MA` for "Muhammad Aditya" — used by the avatar placeholder.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(static fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    public function primaryRole(): ?UserRole
    {
        $role = $this->roles->first();

        return $role === null ? null : UserRole::tryFrom($role->name);
    }

    public function isAdministrator(): bool
    {
        return $this->hasAnyRole(UserRole::administrative());
    }

    public function isMentor(): bool
    {
        return $this->is_mentor;
    }

    public function isManagedByHris(): bool
    {
        return $this->hris_employee_id !== null;
    }

    /**
     * An HRIS-synced account has no password until the person activates it.
     */
    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    /**
     * `Staff Engineer · Engineering` — the subtitle under a person's name.
     */
    public function jobTitle(): string
    {
        return collect([$this->position_name, $this->department_name])
            ->filter()
            ->implode(' · ');
    }
}
