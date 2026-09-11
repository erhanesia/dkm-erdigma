<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MentoringGroupMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_active
 */
class MentoringGroupMember extends Model
{
    /** @use HasFactory<MentoringGroupMemberFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'mentoring_group_id',
        'user_id',
        'joined_at',
        'left_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'left_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MentoringGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MentoringGroup::class, 'mentoring_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<MentoringGroupMember>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
