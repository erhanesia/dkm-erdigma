<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\RecordsActivity;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Key/value application settings editable from the Settings page.
 *
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, RecordsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    /** @var array<int, string> */
    protected array $auditable = ['key', 'value'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @param  Builder<Setting>  $query
     */
    public function scopeInGroup(Builder $query, string $group): void
    {
        $query->where('group', $group);
    }

    /**
     * The stored string coerced back into its declared type.
     */
    public function typedValue(): string|int|float|bool|null
    {
        return match ($this->type) {
            'integer' => $this->value === null ? null : (int) $this->value,
            'float' => $this->value === null ? null : (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            default => $this->value,
        };
    }
}
