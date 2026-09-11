<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Setting>
 */
interface SettingRepositoryInterface extends RepositoryInterface
{
    /**
     * All settings keyed by their `key` column.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function allValues(): array;

    public function get(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null;

    public function put(string $key, string|int|float|bool|null $value): void;

    /**
     * @param  array<string, string|int|float|bool|null>  $values
     */
    public function putMany(array $values): void;

    /**
     * @return Collection<int, Setting>
     */
    public function grouped(string $group): Collection;

    public function flushCache(): void;
}
