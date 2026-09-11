<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AudioZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<AudioZone>
 */
interface AudioZoneRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, AudioZone>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * Active zones with the relations the playback planner needs.
     *
     * @return Collection<int, AudioZone>
     */
    public function activeWithSchedules(): Collection;

    /**
     * @return Collection<int, AudioZone>
     */
    public function orderedActive(): Collection;

    public function findByCode(string $code): ?AudioZone;

    /**
     * @return array<int, string>
     */
    public function options(): array;

    /**
     * Zones with their device counts, for the overview page.
     *
     * @return Collection<int, AudioZone>
     */
    public function withDeviceCounts(): Collection;
}
