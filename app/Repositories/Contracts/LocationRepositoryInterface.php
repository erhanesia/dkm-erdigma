<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Location;

/**
 * @extends RepositoryInterface<Location>
 */
interface LocationRepositoryInterface extends RepositoryInterface
{
    /**
     * Every place, name => name, alphabetically — shaped for a select whose
     * value is the name itself.
     *
     * @return array<string, string>
     */
    public function nameOptions(): array;

    /**
     * The place with this name, matched case-insensitively, created when it is
     * not in the list yet.
     */
    public function firstOrCreateByName(string $name): Location;
}
