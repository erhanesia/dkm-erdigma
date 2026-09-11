<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the immutable data objects passed from controllers into services.
 *
 * Using a DTO instead of the raw request keeps HTTP concerns out of the service
 * layer and makes mass assignment impossible: only declared properties survive
 * the trip.
 */
abstract class BaseData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    abstract public static function fromArray(array $attributes): static;

    public static function fromRequest(FormRequest $request): static
    {
        return static::fromArray($request->validated());
    }

    /**
     * Persistable attributes, with nulls preserved so a field can be cleared.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Same as `toArray()` but with null entries dropped — used for partial
     * updates where an omitted field must keep its stored value.
     *
     * @return array<string, mixed>
     */
    public function toFilledArray(): array
    {
        return array_filter($this->toArray(), static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Read a boolean that may arrive as "1", "on", "true", or missing entirely.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected static function boolean(array $attributes, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $attributes)) {
            return $default;
        }

        return filter_var($attributes[$key], FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function nullableString(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function nullableInt(array $attributes, string $key): ?int
    {
        $value = $attributes[$key] ?? null;

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
