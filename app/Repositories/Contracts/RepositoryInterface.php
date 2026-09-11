<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Persistence contract shared by every repository.
 *
 * Services depend on this interface, never on Eloquent directly, so the storage
 * layer can be swapped or faked in tests without touching business logic.
 *
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     * @return TModel|null
     */
    public function find(int $id, array $relations = []): ?Model;

    /**
     * @param  array<int, string>  $relations
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id, array $relations = []): Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>  $relations
     * @return TModel|null
     */
    public function findBy(array $criteria, array $relations = []): ?Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>  $relations
     * @return Collection<int, TModel>
     */
    public function all(array $criteria = [], array $relations = []): Collection;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(array $criteria = [], array $relations = [], ?int $perPage = null): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function updateOrCreate(array $criteria, array $attributes): Model;

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): bool;

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function count(array $criteria = []): int;

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function exists(array $criteria): bool;

    /**
     * Value list for `<select>` inputs: id => label.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int|string, string>
     */
    public function pluckOptions(string $labelColumn, string $keyColumn = 'id', array $criteria = []): array;
}
