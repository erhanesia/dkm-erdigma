<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\RecordsActivity;
use Database\Factories\MentoringGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A halaqah — one mentor (ustadz) and the employees they guide.
 *
 * @property int $capacity
 * @property bool $is_active
 */
class MentoringGroup extends Model
{
    /** @use HasFactory<MentoringGroupFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'code',
        'mentor_id',
        'capacity',
        'description',
        'default_location',
        'default_location_id',
        'is_active',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['name', 'code', 'mentor_id', 'capacity', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * Where the halaqah usually meets, as a row in the shared place list.
     *
     * @return BelongsTo<Location, $this>
     */
    public function defaultPlace(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * @return HasMany<MentoringGroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(MentoringGroupMember::class);
    }

    /**
     * Currently active members, as User models.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mentoring_group_members')
            ->withPivot(['joined_at', 'left_at', 'is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    /**
     * @return HasMany<AfterHoursSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AfterHoursSession::class);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * @param  Builder<MentoringGroup>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<MentoringGroup>  $query
     */
    public function scopeMentoredBy(Builder $query, int $mentorId): void
    {
        $query->where('mentor_id', $mentorId);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    public function activeMemberCount(): int
    {
        return $this->memberships()->where('is_active', true)->count();
    }

    public function remainingSlots(): int
    {
        return max(0, $this->capacity - $this->activeMemberCount());
    }

    public function isFull(): bool
    {
        return $this->remainingSlots() === 0;
    }
}
