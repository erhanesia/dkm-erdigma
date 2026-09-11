<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic Eloquent persistence. Concrete repositories only declare their model
 * and any query methods specific to their aggregate, so the CRUD plumbing is
 * written exactly once.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Column used when no explicit ordering is requested.
     */
    protected string $defaultOrderColumn = 'id';

    protected string $defaultOrderDirection = 'desc';

    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * A fresh query builder for the repository's model.
     *
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @return TModel
     */
    protected function newModel(): Model
    {
        $class = $this->model();

        return new $class;
    }

    /**
     * Translate a criteria array into where clauses.
     *
     * Supported shapes:
     *  - `['column' => 'value']`            → `where('column', 'value')`
     *  - `['column' => [1, 2, 3]]`          → `whereIn('column', [...])`
     *  - `['column' => null]`               → `whereNull('column')`
     *  - `['column' => ['>=', $value]]`     → `where('column', '>=', $value)`
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $criteria
     * @return Builder<TModel>
     */
    protected function applyCriteria(Builder $query, array $criteria): Builder
    {
        foreach ($criteria as $column => $value) {
            if (is_int($column) && is_callable($value)) {
                $value($query);

                continue;
            }

            if ($value === null) {
                $query->whereNull($column);

                continue;
            }

            if (is_array($value) && count($value) === 2 && is_string($value[0] ?? null) && in_array($value[0], ['=', '!=', '<', '<=', '>', '>=', 'like'], true)) {
                $query->where($column, $value[0], $value[1]);

                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyDefaultOrder(Builder $query): Builder
    {
        return $query->orderBy($this->defaultOrderColumn, $this->defaultOrderDirection);
    }

    // -----------------------------------------------------------------
    // RepositoryInterface
    // -----------------------------------------------------------------

    public function find(int $id, array $relations = []): ?Model
    {
        return $this->query()->with($relations)->find($id);
    }

    public function findOrFail(int $id, array $relations = []): Model
    {
        return $this->query()->with($relations)->findOrFail($id);
    }

    public function findBy(array $criteria, array $relations = []): ?Model
    {
        return $this->applyCriteria($this->query()->with($relations), $criteria)->first();
    }

    public function all(array $criteria = [], array $relations = []): Collection
    {
        $query = $this->applyCriteria($this->query()->with($relations), $criteria);

        return $this->applyDefaultOrder($query)->get();
    }

    public function paginate(array $criteria = [], array $relations = [], ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->applyCriteria($this->query()->with($relations), $criteria);

        return $this->applyDefaultOrder($query)
            ->paginate($perPage ?? (int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function updateOrCreate(array $criteria, array $attributes): Model
    {
        return $this->query()->updateOrCreate($criteria, $attributes);
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function count(array $criteria = []): int
    {
        return $this->applyCriteria($this->query(), $criteria)->count();
    }

    public function exists(array $criteria): bool
    {
        return $this->applyCriteria($this->query(), $criteria)->exists();
    }

    public function pluckOptions(string $labelColumn, string $keyColumn = 'id', array $criteria = []): array
    {
        return $this->applyCriteria($this->query(), $criteria)
            ->orderBy($labelColumn)
            ->pluck($labelColumn, $keyColumn)
            ->all();
    }
}
