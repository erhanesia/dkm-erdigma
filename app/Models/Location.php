<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place a session or a halaqah meets in — typed once, picked afterwards.
 *
 * @property string $name
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<AfterHoursSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AfterHoursSession::class);
    }

    /**
     * Halaqah that name this place as where they usually meet.
     *
     * @return HasMany<MentoringGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(MentoringGroup::class, 'default_location_id');
    }
}
