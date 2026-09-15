<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AfterHoursSessionSeries;
use App\Repositories\Contracts\AfterHoursSessionSeriesRepositoryInterface;

/**
 * @extends BaseRepository<AfterHoursSessionSeries>
 */
class AfterHoursSessionSeriesRepository extends BaseRepository implements AfterHoursSessionSeriesRepositoryInterface
{
    protected function model(): string
    {
        return AfterHoursSessionSeries::class;
    }
}
