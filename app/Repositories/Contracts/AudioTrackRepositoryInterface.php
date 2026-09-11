<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AudioTrackType;
use App\Models\AudioTrack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<AudioTrack>
 */
interface AudioTrackRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, AudioTrack>
     */
    public function paginateFiltered(): LengthAwarePaginator;

    /**
     * @return Collection<int, AudioTrack>
     */
    public function activeOfType(AudioTrackType $type): Collection;

    /**
     * The fallback track used when a zone has not picked one explicitly.
     */
    public function defaultForType(AudioTrackType $type): ?AudioTrack;

    /**
     * Grouped `<optgroup>` data: type label => [id => title].
     *
     * @return array<string, array<int, string>>
     */
    public function groupedOptions(): array;

    /**
     * @return array<int, string>
     */
    public function optionsForTypes(AudioTrackType ...$types): array;

    /**
     * Clear the default flag from every other track of the same type.
     */
    public function clearDefaultFlag(AudioTrackType $type, ?int $exceptId = null): void;
}
