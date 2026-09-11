<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\Contracts\HasLabel;

/**
 * Shared enum utilities so every enum in the application exposes the same
 * helpers instead of each one re-declaring `options()`, `values()`, and friends.
 *
 * @mixin \BackedEnum
 * @mixin HasLabel
 */
trait EnumHelpers
{
    /**
     * All backing values of the enum.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Value => Indonesian label map, ready for `<select>` elements.
     *
     * @return array<string|int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Value => label collection shaped for JSON payloads.
     *
     * @return array<int, array{value: string|int, label: string, color: string, icon: string}>
     */
    public static function toArray(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
            'icon' => $case->icon(),
        ], self::cases());
    }

    /**
     * Resolve a case from a raw value, falling back instead of throwing.
     */
    public static function fromValueOr(mixed $value, ?self $fallback = null): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom($value) ?? $fallback;
    }

    /**
     * Bootstrap badge markup helper, e.g. `text-bg-success`.
     */
    public function badgeClass(): string
    {
        return 'text-bg-'.$this->color();
    }

    public function iconClass(): string
    {
        return 'bi bi-'.$this->icon();
    }

    public function is(self ...$cases): bool
    {
        return in_array($this, $cases, true);
    }
}
