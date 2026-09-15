<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Location;
use App\Repositories\Contracts\LocationRepositoryInterface;

/**
 * @extends BaseRepository<Location>
 */
class LocationRepository extends BaseRepository implements LocationRepositoryInterface
{
    protected string $defaultOrderColumn = 'name';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return Location::class;
    }

    public function nameOptions(): array
    {
        return $this->query()->orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * Reads first; a create that races someone adding the same new place falls
     * back to reading their row, so both forms still land on one location.
     */
    public function firstOrCreateByName(string $name): Location
    {
        return $this->query()->firstOrCreate(['name' => $name]);
    }
}
