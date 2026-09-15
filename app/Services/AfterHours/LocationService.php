<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Support\Str;

/**
 * The shared list of places sessions and halaqah meet in.
 *
 * Forms send a place's name, not its id: the dropdown lets people type a new
 * one, and a typed name and a picked name then arrive the same way.
 */
class LocationService
{
    public function __construct(
        private readonly LocationRepositoryInterface $locations,
    ) {}

    /**
     * Name => name for the dropdown, with `$current` added when it is not in the
     * list — a value saved before the list existed must still show as selected
     * instead of quietly blanking the field.
     *
     * @return array<string, string>
     */
    public function options(?string $current = null): array
    {
        $options = $this->locations->nameOptions();
        $current = Str::squish((string) $current);

        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * The id and the name to store for a place typed or picked in a form.
     *
     * Spacing is tidied first and the lookup ignores case, so "musholla
     * erdigma" lands on the existing "Musholla Erdigma" rather than beside it.
     * A name not in the list yet is added, so the next form offers it.
     *
     * @return array{id: int|null, name: string|null}
     */
    public function resolve(?string $name): array
    {
        $name = Str::squish((string) $name);

        if ($name === '') {
            return ['id' => null, 'name' => null];
        }

        $location = $this->locations->firstOrCreateByName($name);

        return ['id' => $location->id, 'name' => $location->name];
    }
}
