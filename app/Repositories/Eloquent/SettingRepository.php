<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Settings are read on nearly every request, so the whole table is cached as a
 * single array and invalidated on write.
 *
 * @extends BaseRepository<Setting>
 */
class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    private const CACHE_KEY = 'dkm.settings';

    private const CACHE_TTL = 3600;

    protected string $defaultOrderColumn = 'key';

    protected string $defaultOrderDirection = 'asc';

    protected function model(): string
    {
        return Setting::class;
    }

    public function allValues(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $values = [];

            foreach ($this->query()->get() as $setting) {
                $values[$setting->key] = $setting->typedValue();
            }

            return $values;
        });
    }

    public function get(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null
    {
        return $this->allValues()[$key] ?? $default;
    }

    public function put(string $key, string|int|float|bool|null $value): void
    {
        $this->query()
            ->where('key', $key)
            ->update(['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);

        $this->flushCache();
    }

    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->query()
                ->where('key', $key)
                ->update(['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
        }

        $this->flushCache();
    }

    public function grouped(string $group): Collection
    {
        return $this->query()->inGroup($group)->orderBy('id')->get();
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
