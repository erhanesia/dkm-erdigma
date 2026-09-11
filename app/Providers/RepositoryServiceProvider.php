<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts;
use App\Repositories\Eloquent;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository contract to its Eloquent implementation.
 *
 * Services type-hint the interfaces, so swapping a storage strategy or faking a
 * repository in a test is a one-line change here.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Contract => implementation.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        Contracts\AfterHoursSessionRepositoryInterface::class => Eloquent\AfterHoursSessionRepository::class,
        Contracts\AttendanceRepositoryInterface::class => Eloquent\AttendanceRepository::class,
        Contracts\AudioTrackRepositoryInterface::class => Eloquent\AudioTrackRepository::class,
        Contracts\AudioZoneRepositoryInterface::class => Eloquent\AudioZoneRepository::class,
        Contracts\DeviceCommandRepositoryInterface::class => Eloquent\DeviceCommandRepository::class,
        Contracts\DeviceRepositoryInterface::class => Eloquent\DeviceRepository::class,
        Contracts\FridayScheduleRepositoryInterface::class => Eloquent\FridayScheduleRepository::class,
        Contracts\MentoringGroupRepositoryInterface::class => Eloquent\MentoringGroupRepository::class,
        Contracts\MurottalScheduleRepositoryInterface::class => Eloquent\MurottalScheduleRepository::class,
        Contracts\PlaybackLogRepositoryInterface::class => Eloquent\PlaybackLogRepository::class,
        Contracts\PrayerDutyRepositoryInterface::class => Eloquent\PrayerDutyRepository::class,
        Contracts\PrayerScheduleRepositoryInterface::class => Eloquent\PrayerScheduleRepository::class,
        Contracts\SettingRepositoryInterface::class => Eloquent\SettingRepository::class,
        Contracts\UserRepositoryInterface::class => Eloquent\UserRepository::class,
        Contracts\ZonePrayerSettingRepositoryInterface::class => Eloquent\ZonePrayerSettingRepository::class,
    ];
}
