<?php

declare(strict_types=1);

use App\Console\Commands\AnnouncePrayerTimes;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tugas terjadwal
|--------------------------------------------------------------------------
|
| Dijalankan oleh satu cron entry di server:
|
|     * * * * * cd /path/ke/dkm && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
 * Pengingat sholat.
 *
 * Dicek tiap menit karena waktu sholat bergeser setiap hari — menjadwalkan lima
 * tugas pada jam tetap akan salah besok. Hampir semua eksekusi tidak melakukan
 * apa-apa, dan itu memang bentuk yang diinginkan: pengecekannya murah.
 *
 * `withoutOverlapping` menjaga eksekusi yang lambat tidak ditimpa eksekusi
 * berikutnya. Pencegahan kirim ganda sendiri ada di database, jadi ini lapis
 * kedua, bukan satu-satunya.
 */
Schedule::command(AnnouncePrayerTimes::class)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
