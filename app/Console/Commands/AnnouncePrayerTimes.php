<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notification\PrayerAnnouncementService;
use App\Support\Helpers\DateHelper;
use Illuminate\Console\Command;

/**
 * Sends the WhatsApp reminder for any prayer that is about to begin.
 *
 * Runs every minute. Almost every run does nothing, which is the intended shape:
 * checking is cheap, and the alternative — scheduling five one-off jobs a day
 * against times that shift daily — has more ways to go wrong.
 *
 * Sending twice is prevented in the database rather than here, so running this
 * by hand while the scheduler is also running is safe.
 */
class AnnouncePrayerTimes extends Command
{
    protected $signature = 'prayer:announce
                            {--dry-run : Tampilkan pesan yang akan dikirim tanpa mengirimnya}';

    protected $description = 'Kirim pengingat waktu sholat ke grup WhatsApp lewat WOW';

    public function handle(PrayerAnnouncementService $announcements): int
    {
        $now = DateHelper::now();
        $due = $announcements->duePrayers($now);

        if ($due === []) {
            $this->line('Tidak ada pengingat yang jatuh tempo pada '.$now->format('H:i').'.');

            return self::SUCCESS;
        }

        foreach ($due as $item) {
            $prayer = $item['prayer'];

            if ($this->option('dry-run')) {
                $this->components->info($prayer->label().' — '.$item['at']->format('H:i'));
                $this->line($item['date']->isFriday() && $prayer->value === 'dhuhr'
                    ? $announcements->composeFriday($item['at'], null)
                    : $announcements->composePrayer($prayer, $item['at']));
                $this->newLine();

                continue;
            }

            $notification = $announcements->announce($prayer, $item['date'], $item['at']);

            if ($notification === null) {
                $this->line($prayer->label().': sudah dikirim sebelumnya, dilewati.');

                continue;
            }

            $this->components->twoColumnDetail(
                $prayer->label().' — '.$item['at']->format('H:i'),
                $notification->status->label(),
            );

            if ($notification->error !== null) {
                $this->warn('  '.$notification->error);
            }
        }

        return self::SUCCESS;
    }
}
