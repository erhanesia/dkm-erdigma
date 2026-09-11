<?php

declare(strict_types=1);

namespace App\Services\Audio;

use App\Enums\DeviceCommandType;
use App\Exceptions\BusinessRuleException;
use App\Models\AudioZone;
use App\Models\User;
use App\Repositories\Contracts\AudioZoneRepositoryInterface;
use App\Repositories\Contracts\ZonePrayerSettingRepositoryInterface;
use App\Services\Device\DeviceCommandService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Room-level audio configuration.
 *
 * Every change is pushed to the affected players straight away, so turning
 * tilawah back on in a room takes effect without waiting for the next daily
 * sync — and without walking to that room.
 */
class AudioZoneService
{
    public function __construct(
        private readonly AudioZoneRepositoryInterface $zones,
        private readonly ZonePrayerSettingRepositoryInterface $prayerSettings,
        private readonly DeviceCommandService $commands,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AudioZone>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->zones->paginateFiltered();
    }

    /**
     * @return Collection<int, AudioZone>
     */
    public function overview(): Collection
    {
        return $this->zones->withDeviceCounts();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AudioZone
    {
        return DB::transaction(function () use ($attributes): AudioZone {
            $zone = $this->zones->create($attributes);

            $this->prayerSettings->seedDefaults($zone);

            return $zone;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AudioZone $zone, array $attributes, ?User $actor = null): AudioZone
    {
        $updated = $this->zones->update($zone, $attributes);

        $this->pushPlanReload($updated, $actor);

        return $updated;
    }

    public function delete(AudioZone $zone): void
    {
        if ($zone->devices()->exists()) {
            throw new BusinessRuleException(
                'Zona ini masih memiliki perangkat terdaftar. Pindahkan atau hapus perangkatnya terlebih dahulu.',
            );
        }

        $this->zones->delete($zone);
    }

    /**
     * The one-click switch for "matikan tilawah di ruangan CEO".
     */
    public function toggleMurottal(AudioZone $zone, bool $enabled, ?User $actor = null): AudioZone
    {
        $updated = $this->zones->update($zone, ['is_murottal_enabled' => $enabled]);

        if (! $enabled) {
            $this->commands->dispatchToZone($updated, DeviceCommandType::Stop, issuer: $actor);
        }

        $this->pushPlanReload($updated, $actor);

        return $updated;
    }

    public function toggleAdhan(AudioZone $zone, bool $enabled, ?User $actor = null): AudioZone
    {
        $updated = $this->zones->update($zone, ['is_adhan_enabled' => $enabled]);

        $this->pushPlanReload($updated, $actor);

        return $updated;
    }

    public function toggleActive(AudioZone $zone, bool $active, ?User $actor = null): AudioZone
    {
        $updated = $this->zones->update($zone, ['is_active' => $active]);

        if (! $active) {
            $this->commands->dispatchToZone($updated, DeviceCommandType::Stop, issuer: $actor);
        }

        $this->pushPlanReload($updated, $actor);

        return $updated;
    }

    /**
     * Save the per-prayer adhan grid for one room.
     *
     * @param  array<string, array<string, mixed>>  $settingsByPrayer
     */
    public function savePrayerSettings(AudioZone $zone, array $settingsByPrayer, ?User $actor = null): void
    {
        DB::transaction(function () use ($zone, $settingsByPrayer): void {
            $this->prayerSettings->saveForZone($zone, $settingsByPrayer);
        });

        $this->pushPlanReload($zone, $actor);
    }

    public function setVolume(AudioZone $zone, int $volume, ?User $actor = null): AudioZone
    {
        $updated = $this->zones->update($zone, ['default_volume' => $volume]);

        $this->commands->dispatchToZone($updated, DeviceCommandType::SetVolume, ['volume' => $volume], $actor);

        return $updated;
    }

    /**
     * Tell every player in the room to re-fetch its plan.
     */
    public function pushPlanReload(AudioZone $zone, ?User $actor = null): void
    {
        $this->commands->dispatchToZone($zone, DeviceCommandType::ReloadPlan, issuer: $actor);
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->zones->options();
    }
}
