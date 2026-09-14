<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AudioTrackType;
use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\DeviceStatus;
use App\Enums\NotificationKind;
use App\Enums\NotificationStatus;
use App\Enums\PlaybackStatus;
use App\Enums\PlaybackType;
use App\Enums\PrayerName;
use App\Models\AudioTrack;
use App\Models\AudioZone;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceHeartbeat;
use App\Models\FridaySchedule;
use App\Models\MurottalSchedule;
use App\Models\PlaybackLog;
use App\Models\PrayerSchedule;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Models\ZonePrayerSetting;
use App\Services\Notification\PrayerAnnouncementService;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The audio side of the app as it looks after two months in use: a track
 * library, murottal schedules, players in every zone, and what each of them
 * reported.
 *
 * The admin dashboard and the monitoring page are built to answer "is the adhan
 * actually being heard?", and with no players and no playback history they
 * could only ever answer "nothing has happened". The devices here cover every
 * state a board runs into — online, muted, offline, never connected, disabled —
 * and the playback history follows from them: the offline player's zone has
 * gone quiet since it dropped, the muted one's has been silent for three days,
 * and the recent problems are left unacknowledged for someone to look at.
 *
 * Two limits worth knowing:
 *
 * - Online is measured from the last heartbeat, and nothing real is sending
 *   one. The two "online" players read as offline a few minutes after seeding.
 * - No audio files ship with the demo. The tracks list, filter and assign like
 *   real ones; playing one needs a real upload.
 */
class DemoAudioOperationsSeeder extends Seeder
{
    private const PLAYBACK_DAYS = 60;

    private const HEARTBEAT_HOURS = 24;

    private const HEARTBEAT_INTERVAL_MINUTES = 5;

    private const NOTIFICATION_DAYS = 30;

    /** Problems newer than this are left for the board to acknowledge. */
    private const OPEN_ISSUE_DAYS = 3;

    /** How long the muted player has been silent. */
    private const MUTED_DAYS = 3;

    /**
     * Title => type, reciter, duration in seconds.
     *
     * @var array<string, array{0: AudioTrackType, 1: string|null, 2: int}>
     */
    private const TRACKS = [
        'Adzan Makkah — Syaikh Ali Ahmad Mulla' => [AudioTrackType::Adhan, 'Syaikh Ali Ahmad Mulla', 185],
        'Adzan Madinah — Syaikh Essam Bukhari' => [AudioTrackType::Adhan, 'Syaikh Essam Bukhari', 172],
        'Adzan Merdu — Muammar ZA' => [AudioTrackType::Adhan, 'Muammar ZA', 160],
        'Adzan Subuh — Syaikh Ali Ahmad Mulla' => [AudioTrackType::AdhanFajr, 'Syaikh Ali Ahmad Mulla', 210],
        'Iqamah Standar' => [AudioTrackType::Iqamah, null, 45],
        'Iqamah Pelan' => [AudioTrackType::Iqamah, null, 58],
        'Tarhim Subuh — Syaikh Mahmud Khalil Al-Hushari' => [AudioTrackType::Tarhim, 'Syaikh Mahmud Khalil Al-Hushari', 420],
        'Sholawat Tarhim Maghrib' => [AudioTrackType::Tarhim, null, 360],
        'Surat Al-Kahfi — Mishary Rashid Alafasy' => [AudioTrackType::Murottal, 'Mishary Rashid Alafasy', 2640],
        'Surat Yasin — Mishary Rashid Alafasy' => [AudioTrackType::Murottal, 'Mishary Rashid Alafasy', 1080],
        'Surat Ar-Rahman — Abdul Basit Abdus Samad' => [AudioTrackType::Murottal, 'Abdul Basit Abdus Samad', 900],
        'Surat Al-Mulk — Saad Al-Ghamdi' => [AudioTrackType::Murottal, 'Saad Al-Ghamdi', 480],
        'Juz Amma — Muzammil Hasballah' => [AudioTrackType::Murottal, 'Muzammil Hasballah', 3300],
        'Surat Al-Waqiah — Maher Al-Muaiqly' => [AudioTrackType::Murottal, 'Maher Al-Muaiqly', 720],
        'Pengumuman Sholat Jumat' => [AudioTrackType::Announcement, null, 35],
        'Nada Uji 1 kHz' => [AudioTrackType::TestTone, null, 10],
    ];

    /**
     * Device name => zone code, the state it is left in.
     *
     * @var array<string, array{0: string, 1: DeviceStatus}>
     */
    private const DEVICES = [
        'Player Musholla' => ['musholla', DeviceStatus::Online],
        'Player Lobby' => ['lobby', DeviceStatus::Online],
        'Player Ruang Kerja' => ['ruang-kerja', DeviceStatus::Muted],
        'Player Ruangan CEO' => ['ruang-ceo', DeviceStatus::Offline],
        'Player Ruang Meeting' => ['ruang-meeting', DeviceStatus::NeverConnected],
        'Player Cadangan Musholla' => ['musholla', DeviceStatus::Disabled],
    ];

    /** The zone that also plays tarhim before Subuh and iqamah after Dzuhur and Ashar. */
    private const MAIN_ZONE = 'musholla';

    public function run(PrayerScheduleService $prayerSchedules, PrayerAnnouncementService $announcements): void
    {
        fake()->seed(20260916);

        $admin = User::query()->where('email', 'dkm@erdigma.id')->first();
        $zones = AudioZone::query()->orderBy('sort_order')->get()->keyBy('code');

        if ($admin === null || $zones->isEmpty()) {
            $this->command?->warn('Lewati data audio: akun pengurus DKM atau zona audio belum ada.');

            return;
        }

        $tracks = $this->seedTracks($admin);
        $this->assignZoneTracks($tracks);
        $this->seedMurottal($zones, $tracks);

        $devices = $this->seedDevices($zones);
        $this->seedHeartbeats($devices);
        $this->seedCommands($devices, $tracks, $admin);
        $this->seedPlaybackLogs($zones, $devices, $tracks, $prayerSchedules, $admin);
        $this->seedNotifications($prayerSchedules, $announcements);
    }

    /**
     * @return Collection<string, AudioTrack> Keyed by title.
     */
    private function seedTracks(User $admin): Collection
    {
        $tracks = collect();
        $defaultTaken = [];

        foreach (self::TRACKS as $title => [$type, $reciter, $duration]) {
            $slug = Str::slug($title);

            $tracks[$title] = AudioTrack::query()->firstOrCreate(
                ['title' => $title],
                [
                    'type' => $type->value,
                    'reciter' => $reciter,
                    // A placeholder: no audio file ships with the demo.
                    'file_path' => 'audio/demo/'.$slug.'.mp3',
                    'original_name' => $slug.'.mp3',
                    'mime_type' => 'audio/mpeg',
                    // 128 kbps.
                    'file_size' => $duration * 16_000,
                    'duration_seconds' => $duration,
                    'is_default' => ! isset($defaultTaken[$type->value]),
                    'is_active' => true,
                    'uploaded_by' => $admin->id,
                ],
            );

            $defaultTaken[$type->value] = true;
        }

        $this->command?->info(count(self::TRACKS).' audio demo tersedia di pustaka.');

        return $tracks;
    }

    /**
     * Gives every zone's prayer settings an adhan, a tarhim and an iqamah, where
     * none was chosen yet.
     *
     * @param  Collection<string, AudioTrack>  $tracks
     */
    private function assignZoneTracks(Collection $tracks): void
    {
        $adhan = $this->firstOfType($tracks, AudioTrackType::Adhan);
        $adhanFajr = $this->firstOfType($tracks, AudioTrackType::AdhanFajr) ?? $adhan;
        $tarhim = $this->firstOfType($tracks, AudioTrackType::Tarhim);
        $iqamah = $this->firstOfType($tracks, AudioTrackType::Iqamah);

        $settings = ZonePrayerSetting::query()->whereNull('adhan_track_id')->get();

        foreach ($settings as $setting) {
            $setting->update([
                'adhan_track_id' => ($setting->prayer === PrayerName::Fajr ? $adhanFajr : $adhan)?->id,
                'tarhim_track_id' => $tarhim?->id,
                'iqamah_track_id' => $iqamah?->id,
            ]);
        }

        $this->command?->info($settings->count().' pengaturan sholat per zona diberi audio.');
    }

    /**
     * @param  Collection<string, AudioZone>  $zones
     * @param  Collection<string, AudioTrack>  $tracks
     */
    private function seedMurottal(Collection $zones, Collection $tracks): void
    {
        $murottal = $tracks->filter(static fn (AudioTrack $track): bool => $track->type === AudioTrackType::Murottal)->values();

        if ($murottal->isEmpty()) {
            return;
        }

        $kahfi = $murottal->first(static fn (AudioTrack $track): bool => str_contains($track->title, 'Al-Kahfi')) ?? $murottal->first();

        // Name, start, end, ISO weekdays, whether it is the Friday Al-Kahfi.
        // Friday has its own morning slot, so the weekday ones stop at Thursday
        // rather than overlapping it.
        $plans = [
            ['Murottal Pagi', '07:30', '08:00', [1, 2, 3, 4], false],
            ['Murottal Menjelang Dzuhur', '11:10', '11:40', [1, 2, 3, 4], false],
            ['Al-Kahfi Jumat Pagi', '07:00', '07:45', [5], true],
        ];

        $created = 0;
        $murottalZones = $zones->filter(static fn (AudioZone $zone): bool => $zone->is_active && $zone->is_murottal_enabled)->values();

        foreach ($murottalZones as $zoneIndex => $zone) {
            foreach ($plans as $planIndex => [$name, $start, $end, $days, $isKahfi]) {
                $schedule = MurottalSchedule::query()->firstOrCreate(
                    ['audio_zone_id' => $zone->id, 'name' => $name],
                    [
                        'audio_track_id' => ($isKahfi ? $kahfi : $murottal[($zoneIndex + $planIndex) % $murottal->count()])->id,
                        'start_time' => $start,
                        'end_time' => $end,
                        'days_of_week' => $days,
                        'volume' => $zone->default_volume ?? 60,
                        'is_loop' => true,
                        'stops_before_adhan' => true,
                        'is_active' => true,
                    ],
                );

                $created += $schedule->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info($created.' jadwal tilawah demo dibuat.');
    }

    /**
     * @param  Collection<string, AudioZone>  $zones
     * @return Collection<string, Device> Keyed by name.
     */
    private function seedDevices(Collection $zones): Collection
    {
        $now = DateHelper::now();
        $devices = collect();

        foreach (self::DEVICES as $name => [$zoneCode, $state]) {
            $zone = $zones->get($zoneCode);

            if ($zone === null) {
                continue;
            }

            $lastSeen = match ($state) {
                DeviceStatus::Online, DeviceStatus::Muted => $now->subSeconds(fake()->numberBetween(5, 40)),
                DeviceStatus::Offline => $now->subDays(2)->subMinutes(fake()->numberBetween(10, 300)),
                DeviceStatus::Disabled => $now->subDays(20),
                DeviceStatus::NeverConnected => null,
            };

            $token = TokenHelper::generateDeviceToken();

            $devices[$name] = Device::query()->firstOrCreate(
                ['name' => $name],
                [
                    'audio_zone_id' => $zone->id,
                    'token_hash' => $token['hash'],
                    'token_preview' => $token['preview'],
                    'status' => $state->value,
                    'volume' => fake()->numberBetween(65, 90),
                    'is_audio_unlocked' => $lastSeen !== null && $state !== DeviceStatus::Muted,
                    'last_audio_level' => match ($state) {
                        DeviceStatus::Online => fake()->randomFloat(4, 0.12, 0.45),
                        DeviceStatus::NeverConnected => null,
                        default => 0,
                    },
                    'app_version' => $lastSeen === null ? null : '1.4.2',
                    'user_agent' => $lastSeen === null ? null : 'Mozilla/5.0 (Linux; Android 12; TV Box) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                    'last_ip_address' => $lastSeen === null ? null : '192.168.10.'.fake()->numberBetween(20, 80),
                    'last_seen_at' => $lastSeen?->toDateTimeString(),
                    'plan_synced_at' => $lastSeen?->subMinutes(fake()->numberBetween(1, 30))->toDateTimeString(),
                    'is_active' => $state !== DeviceStatus::Disabled,
                    'notes' => match ($state) {
                        DeviceStatus::Offline => 'Adaptor sering longgar, cek kabel power.',
                        DeviceStatus::Disabled => 'Cadangan, dinyalakan kalau player utama rusak.',
                        DeviceStatus::NeverConnected => 'Belum dipasang, menunggu TV box baru.',
                        default => null,
                    },
                ],
            );
        }

        $this->command?->info($devices->count().' perangkat demo tersedia.');

        return $devices;
    }

    /**
     * A day of heartbeats leading up to each player's last contact.
     *
     * @param  Collection<string, Device>  $devices
     */
    private function seedHeartbeats(Collection $devices): void
    {
        $created = 0;
        $beats = intdiv(self::HEARTBEAT_HOURS * 60, self::HEARTBEAT_INTERVAL_MINUTES);

        foreach ($devices as $device) {
            if ($device->last_seen_at === null || $device->heartbeats()->exists()) {
                continue;
            }

            $rows = [];

            for ($beat = 0; $beat < $beats; $beat++) {
                $rows[] = [
                    'device_id' => $device->id,
                    'is_audio_unlocked' => $device->is_audio_unlocked,
                    'audio_level' => $device->is_audio_unlocked ? fake()->randomFloat(4, 0, 0.35) : 0,
                    'volume' => $device->volume,
                    'is_online_browser' => true,
                    'app_version' => $device->app_version,
                    'reported_at' => $device->last_seen_at->subMinutes($beat * self::HEARTBEAT_INTERVAL_MINUTES)->toDateTimeString(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DeviceHeartbeat::query()->insert($chunk);
            }

            $created += count($rows);
        }

        $this->command?->info($created.' heartbeat perangkat demo dibuat.');
    }

    /**
     * Commands the board sent over the last two weeks, and how each one went.
     *
     * @param  Collection<string, Device>  $devices
     * @param  Collection<string, AudioTrack>  $tracks
     */
    private function seedCommands(Collection $devices, Collection $tracks, User $admin): void
    {
        $now = DateHelper::now();
        $created = 0;

        foreach ($devices as $device) {
            if ($device->last_seen_at === null || $device->commands()->exists()) {
                continue;
            }

            $rows = [];

            for ($number = 0; $number < 8; $number++) {
                $type = fake()->randomElement(DeviceCommandType::cases());
                $issuedAt = $device->last_seen_at->subMinutes(fake()->numberBetween(60, 14 * 24 * 60));
                $status = DeviceCommandStatus::from($this->weighted([
                    DeviceCommandStatus::Acknowledged->value => 85,
                    DeviceCommandStatus::Failed->value => 10,
                    DeviceCommandStatus::Expired->value => 5,
                ]));

                $rows[] = $this->commandRow($device, $admin, $type, $status, $issuedAt, $tracks);
            }

            // The offline player still has one waiting, sent after it dropped.
            if ($device->resolveStatus() === DeviceStatus::Offline) {
                $rows[] = $this->commandRow($device, $admin, DeviceCommandType::ReloadPlan, DeviceCommandStatus::Pending, $now->subMinutes(30), $tracks);
            }

            DeviceCommand::query()->insert($rows);
            $created += count($rows);
        }

        $this->command?->info($created.' perintah perangkat demo dibuat.');
    }

    /**
     * @param  Collection<string, AudioTrack>  $tracks
     * @return array<string, mixed>
     */
    private function commandRow(Device $device, User $admin, DeviceCommandType $type, DeviceCommandStatus $status, CarbonImmutable $issuedAt, Collection $tracks): array
    {
        $payload = match ($type) {
            DeviceCommandType::PlayTrack => ['track_id' => fake()->randomElement($tracks->values()->all())->id],
            DeviceCommandType::SetVolume => ['volume' => fake()->numberBetween(50, 95)],
            default => [],
        };

        return [
            'device_id' => $device->id,
            'issued_by' => $admin->id,
            'type' => $type->value,
            'payload' => json_encode($payload),
            'status' => $status->value,
            'result_message' => match ($status) {
                DeviceCommandStatus::Acknowledged => 'OK',
                DeviceCommandStatus::Failed => 'Autoplay diblokir browser.',
                default => null,
            },
            'delivered_at' => in_array($status, [DeviceCommandStatus::Acknowledged, DeviceCommandStatus::Failed], true)
                ? $issuedAt->addSeconds(fake()->numberBetween(2, 15))->toDateTimeString()
                : null,
            'acknowledged_at' => $status === DeviceCommandStatus::Acknowledged
                ? $issuedAt->addSeconds(fake()->numberBetween(16, 40))->toDateTimeString()
                : null,
            'expires_at' => $issuedAt->addMinutes(5)->toDateTimeString(),
            'created_at' => $issuedAt->toDateTimeString(),
            'updated_at' => $issuedAt->toDateTimeString(),
        ];
    }

    /**
     * Two months of playback slots for every zone whose player has ever
     * connected — a player that never paired never received a plan, so its
     * zone has no slots to report on.
     *
     * @param  Collection<string, AudioZone>  $zones
     * @param  Collection<string, Device>  $devices
     * @param  Collection<string, AudioTrack>  $tracks
     */
    private function seedPlaybackLogs(Collection $zones, Collection $devices, Collection $tracks, PrayerScheduleService $prayerSchedules, User $admin): void
    {
        $today = DateHelper::today();
        $from = $today->subDays(self::PLAYBACK_DAYS - 1);

        $schedules = $prayerSchedules->forRange($from, $today)->keyBy(
            static fn (PrayerSchedule $schedule): string => DateHelper::toCarbon($schedule->date)->toDateString(),
        );

        $adhan = $this->firstOfType($tracks, AudioTrackType::Adhan);
        $adhanFajr = $this->firstOfType($tracks, AudioTrackType::AdhanFajr) ?? $adhan;
        $tarhim = $this->firstOfType($tracks, AudioTrackType::Tarhim);
        $iqamah = $this->firstOfType($tracks, AudioTrackType::Iqamah);

        if ($adhan === null) {
            return;
        }

        $created = 0;

        foreach ($zones as $zone) {
            $device = $devices->first(static fn (Device $device): bool => $device->audio_zone_id === $zone->id && $device->is_active);

            if (! $zone->is_active || ! $zone->is_adhan_enabled || $device?->last_seen_at === null) {
                continue;
            }

            // Type, prayer, minutes from the prayer time, track.
            $slots = [
                [PlaybackType::Adhan, PrayerName::Fajr, 0, $adhanFajr],
                [PlaybackType::Adhan, PrayerName::Dhuhr, 0, $adhan],
                [PlaybackType::Adhan, PrayerName::Asr, 0, $adhan],
                [PlaybackType::Adhan, PrayerName::Maghrib, 0, $adhan],
                [PlaybackType::Adhan, PrayerName::Isha, 0, $adhan],
            ];

            if ($zone->code === self::MAIN_ZONE && $tarhim !== null && $iqamah !== null) {
                $slots[] = [PlaybackType::Tarhim, PrayerName::Fajr, -10, $tarhim];
                $slots[] = [PlaybackType::Iqamah, PrayerName::Dhuhr, 10, $iqamah];
                $slots[] = [PlaybackType::Iqamah, PrayerName::Asr, 10, $iqamah];
            }

            $existing = PlaybackLog::query()->where('audio_zone_id', $zone->id)->pluck('reference_key')->flip();
            $rows = [];

            for ($date = $from; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
                $schedule = $schedules->get($date->toDateString());

                if ($schedule === null) {
                    continue;
                }

                foreach ($slots as [$type, $prayer, $offset, $track]) {
                    // The same `date:type:prayer` key PlaybackPlanBuilder writes.
                    $key = $date->toDateString().':'.$type->value.':'.$prayer->value;

                    if ($existing->has($key)) {
                        continue;
                    }

                    $rows[] = $this->playbackRow($zone, $device, $type, $prayer, $track, $key, $schedule->momentFor($prayer)->addMinutes($offset), $admin);
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                PlaybackLog::query()->insert($chunk);
            }

            $created += count($rows);
        }

        $this->command?->info($created.' log playback demo dibuat.');
    }

    /**
     * @return array<string, mixed>
     */
    private function playbackRow(AudioZone $zone, Device $device, PlaybackType $type, PrayerName $prayer, AudioTrack $track, string $key, CarbonImmutable $scheduledAt, User $admin): array
    {
        $now = DateHelper::now();
        $deviceStatus = $device->resolveStatus();

        $status = match (true) {
            $scheduledAt->greaterThan($now) => PlaybackStatus::Pending,
            $deviceStatus === DeviceStatus::Offline && $scheduledAt->greaterThan($device->last_seen_at) => PlaybackStatus::Missed,
            ! $device->is_audio_unlocked && $scheduledAt->greaterThan($now->subDays(self::MUTED_DAYS)) => PlaybackStatus::Silent,
            default => PlaybackStatus::from($this->weighted([
                PlaybackStatus::Played->value => 93,
                PlaybackStatus::Silent->value => 2,
                PlaybackStatus::Failed->value => 2,
                PlaybackStatus::Missed->value => 3,
            ])),
        };

        $reported = in_array($status, [PlaybackStatus::Played, PlaybackStatus::Silent, PlaybackStatus::Failed], true);
        $startedAt = $reported ? $scheduledAt->addSeconds(fake()->numberBetween(0, 4)) : null;
        $finishedAt = in_array($status, [PlaybackStatus::Played, PlaybackStatus::Silent], true)
            ? $startedAt?->addSeconds($track->duration_seconds ?? 180)
            : null;
        $acknowledged = $status->isProblematic() && $scheduledAt->lessThan($now->subDays(self::OPEN_ISSUE_DAYS));

        return [
            'audio_zone_id' => $zone->id,
            'device_id' => $reported ? $device->id : null,
            'audio_track_id' => $track->id,
            'type' => $type->value,
            'prayer' => $prayer->value,
            'reference_key' => $key,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'started_at' => $startedAt?->toDateTimeString(),
            'finished_at' => $finishedAt?->toDateTimeString(),
            'status' => $status->value,
            'peak_audio_level' => match ($status) {
                PlaybackStatus::Played => fake()->randomFloat(4, 0.35, 0.85),
                PlaybackStatus::Silent => fake()->randomFloat(4, 0.001, 0.008),
                default => null,
            },
            'average_audio_level' => match ($status) {
                PlaybackStatus::Played => fake()->randomFloat(4, 0.12, 0.35),
                PlaybackStatus::Silent => fake()->randomFloat(4, 0.0005, 0.004),
                default => null,
            },
            'volume' => $device->volume,
            'failure_reason' => match ($status) {
                PlaybackStatus::Silent => 'Audio berjalan tetapi level suaranya hampir nol.',
                PlaybackStatus::Failed => fake()->randomElement(['Autoplay diblokir browser.', 'File audio gagal dimuat.']),
                PlaybackStatus::Missed => 'Tidak ada laporan dari perangkat dalam '
                    .DateHelper::humanDuration((int) config('dkm.playback.missed_threshold')).' setelah jadwal.',
                default => null,
            },
            'is_acknowledged' => $acknowledged,
            'acknowledged_by' => $acknowledged ? $admin->id : null,
            'acknowledged_at' => $acknowledged ? $scheduledAt->addHours(fake()->numberBetween(1, 20))->toDateTimeString() : null,
            // Slots are reserved when the plan is built, the day before.
            'created_at' => $scheduledAt->subDay()->toDateTimeString(),
            'updated_at' => ($finishedAt ?? $scheduledAt)->toDateTimeString(),
        ];
    }

    /**
     * WhatsApp reminders for Dzuhur and Ashar on working days, with the text the
     * app itself composes — Friday's carries that week's roster.
     */
    private function seedNotifications(PrayerScheduleService $prayerSchedules, PrayerAnnouncementService $announcements): void
    {
        $today = DateHelper::today();
        $now = DateHelper::now();
        $lead = (int) config('dkm.whatsapp.announcement.lead_minutes');
        $from = $today->subDays(self::NOTIFICATION_DAYS - 1);

        $schedules = $prayerSchedules->forRange($from, $today)->keyBy(
            static fn (PrayerSchedule $schedule): string => DateHelper::toCarbon($schedule->date)->toDateString(),
        );

        $created = 0;

        for ($date = $from; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            $schedule = $schedules->get($date->toDateString());

            if (! $date->isWeekday() || $schedule === null) {
                continue;
            }

            foreach ([PrayerName::Dhuhr, PrayerName::Asr] as $prayer) {
                $at = $schedule->momentFor($prayer);
                $sentAt = $at->subMinutes($lead);

                if ($sentAt->greaterThan($now)) {
                    continue;
                }

                $isFridayPrayer = $prayer === PrayerName::Dhuhr && $date->isFriday();
                $status = NotificationStatus::from($this->weighted([
                    NotificationStatus::Sent->value => 90,
                    NotificationStatus::Failed->value => 5,
                    NotificationStatus::Skipped->value => 5,
                ]));

                $notification = WhatsappNotification::query()->firstOrCreate(
                    [
                        'kind' => ($isFridayPrayer ? NotificationKind::FridayReminder : NotificationKind::PrayerReminder)->value,
                        'reference_date' => $date->toDateString(),
                        'prayer' => $prayer->value,
                    ],
                    [
                        'status' => $status->value,
                        'message' => $isFridayPrayer
                            ? $announcements->composeFriday($at, FridaySchedule::query()->whereDate('date', $date->toDateString())->first())
                            : $announcements->composePrayer($prayer, $at),
                        'attempts' => match ($status) {
                            NotificationStatus::Sent => 1,
                            NotificationStatus::Failed => 3,
                            default => 0,
                        },
                        'error' => match ($status) {
                            NotificationStatus::Failed => 'Koneksi ke WOW habis waktu.',
                            NotificationStatus::Skipped => 'WOW belum dikonfigurasi.',
                            default => null,
                        },
                        'sent_at' => $status === NotificationStatus::Sent ? $sentAt->toDateTimeString() : null,
                        'created_at' => $sentAt->toDateTimeString(),
                        'updated_at' => $sentAt->toDateTimeString(),
                    ],
                );

                $created += $notification->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info($created.' notifikasi WhatsApp demo dibuat.');
    }

    /**
     * @param  Collection<string, AudioTrack>  $tracks
     */
    private function firstOfType(Collection $tracks, AudioTrackType $type): ?AudioTrack
    {
        return $tracks->first(static fn (AudioTrack $track): bool => $track->type === $type);
    }

    /**
     * One key from `value => weight`, chosen in proportion to its weight.
     *
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }
}
