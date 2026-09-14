<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\Auth\ProfileController;
use App\Http\Controllers\Web\CheckInController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Master\AudioTrackController;
use App\Http\Controllers\Web\Master\AudioZoneController;
use App\Http\Controllers\Web\Master\MurottalScheduleController;
use App\Http\Controllers\Web\Master\UserController;
use App\Http\Controllers\Web\Mentoring\AttendanceController;
use App\Http\Controllers\Web\Mentoring\MentoringGroupController;
use App\Http\Controllers\Web\Mentoring\MyAfterHoursController;
use App\Http\Controllers\Web\Mentoring\SessionController;
use App\Http\Controllers\Web\Monitoring\DeviceController;
use App\Http\Controllers\Web\Monitoring\PlaybackLogController;
use App\Http\Controllers\Web\PlayerController;
use App\Http\Controllers\Web\Portal\FridayScheduleController as PortalFridayScheduleController;
use App\Http\Controllers\Web\Portal\HomeController as PortalHomeController;
use App\Http\Controllers\Web\Portal\MatsuratController as PortalMatsuratController;
use App\Http\Controllers\Web\Portal\PrayerScheduleController as PortalPrayerScheduleController;
use App\Http\Controllers\Web\Portal\QuranController as PortalQuranController;
use App\Http\Controllers\Web\Portal\SessionController as PortalSessionController;
use App\Http\Controllers\Web\PrayerCalibrationController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\Schedule\FridayScheduleController;
use App\Http\Controllers\Web\Schedule\PrayerDutyController;
use App\Http\Controllers\Web\Schedule\PrayerScheduleController;
use App\Http\Controllers\Web\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

/*
 * The public site.
 *
 * Open to anyone, so it carries only what a mosque already puts on its
 * noticeboard: prayer times, the Friday roster, and the after-hours sessions it has
 * chosen to announce. Nothing here reads an employee list, an attendance record or a
 * device status — those all live behind /panel.
 */
Route::name('portal.')->group(function (): void {
    Route::get('/', PortalHomeController::class)->name('home');
    Route::get('jadwal-sholat', PortalPrayerScheduleController::class)->name('prayer-schedules');
    /*
     * Detail pages get real URLs rather than a modal, because the point of a
     * public site is that a link can be pasted into a group chat. A modal has
     * no address to paste, no link preview, and no back button.
     *
     * Friday is addressed by its date — unique on that table, readable in a
     * link, and unchanged when the record is edited.
     */
    Route::get('jadwal-jumat', [PortalFridayScheduleController::class, 'index'])->name('friday-schedules');
    Route::get('jadwal-jumat/{date}', [PortalFridayScheduleController::class, 'show'])->name('friday-schedules.show');

    /*
     * Al-Ma'tsurat. The text ships with the repository rather than coming from
     * an API, so this page works with the network unplugged.
     */
    Route::get('al-matsurat', PortalMatsuratController::class)->name('matsurat');

    /*
     * The Qur'an. The one part of this site that is useful to a passer-by
     * rather than to the office, so it needs no account at all.
     */
    Route::get('quran', [PortalQuranController::class, 'index'])->name('quran');
    Route::get('quran/{surah}', [PortalQuranController::class, 'show'])
        ->whereNumber('surah')
        ->name('quran.show');

    Route::get('after-hours', [PortalSessionController::class, 'index'])->name('sessions');
    Route::get('after-hours/{session}', [PortalSessionController::class, 'show'])
        ->whereNumber('session')
        ->name('sessions.show');
});

/*
 * The in-room player. Authentication happens inside the page with the device
 * token, not with a user session, so a kiosk browser never has to stay logged in
 * as a person.
 */
Route::get('player', [PlayerController::class, 'show'])->name('player.show');

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('masuk', [LoginController::class, 'create'])->name('login');
    Route::post('masuk', [LoginController::class, 'store'])->middleware('throttle:login');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

/*
 * Everything below lives under /panel.
 *
 * The public site owns the plain URLs — /jadwal-sholat is what someone would
 * paste into a group chat — so the management app is prefixed rather than
 * competing for them. Only the paths move; the route names stay the same, which
 * is why no view needed editing.
 */
Route::middleware('auth')->prefix('panel')->group(function (): void {
    Route::post('keluar', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // ---- Profil sendiri -------------------------------------------------
    Route::singleton('profil', ProfileController::class)
        ->only(['edit', 'update'])
        ->names(['edit' => 'profile.edit', 'update' => 'profile.update']);

    Route::put('profil/password', PasswordController::class)->name('profile.password.update');

    /*
     * After hours from the member's side — read-only, and scoped to whoever is
     * signed in. Deliberately outside the `role:` group below: an employee is
     * exactly who needs this, and they are the one role that group excludes.
     */
    Route::get('after-hours-saya', MyAfterHoursController::class)->name('my-after-hours.index');

    // ---- Presensi mandiri via QR ---------------------------------------
    Route::get('presensi/{token}', [CheckInController::class, 'show'])->name('attendance.check-in');
    Route::post('presensi/{token}', [CheckInController::class, 'store'])->name('attendance.check-in.store');

    // ---- Jadwal sholat (dapat dilihat semua peran) ----------------------
    Route::get('jadwal-sholat', [PrayerScheduleController::class, 'index'])->name('prayer-schedules.index');

    // ---- Jadwal Jum'at (dapat dilihat semua peran) ----------------------
    Route::get('jadwal-jumat', [FridayScheduleController::class, 'index'])->name('friday-schedules.index');

    /*
    |----------------------------------------------------------------------
    | Mentor & pengurus — halaqah dan kegiatan after hours
    |----------------------------------------------------------------------
    */
    Route::middleware('role:'.implode('|', [UserRole::Mentor->value, ...UserRole::administrative()]))
        ->group(function (): void {
            Route::resource('halaqah', MentoringGroupController::class)
                ->parameters(['halaqah' => 'mentoringGroup'])
                ->names('mentoring-groups');

            Route::resource('kegiatan', SessionController::class)
                ->parameters(['kegiatan' => 'afterHoursSession'])
                ->names('sessions');

            Route::get('kegiatan/{afterHoursSession}/presensi', [AttendanceController::class, 'edit'])
                ->name('attendances.edit');
            Route::put('kegiatan/{afterHoursSession}/presensi', [AttendanceController::class, 'update'])
                ->name('attendances.update');
            Route::get('kegiatan/{afterHoursSession}/qr', [SessionController::class, 'qrCode'])
                ->name('sessions.qr');
            Route::post('kegiatan/{afterHoursSession}/qr', [SessionController::class, 'rotateQr'])
                ->name('sessions.qr.rotate');

            Route::get('laporan/kehadiran', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('laporan/kehadiran/ekspor', [ReportController::class, 'exportAttendance'])
                ->name('reports.attendance.export');
        });

    /*
    |----------------------------------------------------------------------
    | Pengurus DKM — audio, perangkat, jadwal petugas, pengaturan
    |----------------------------------------------------------------------
    */
    Route::middleware('role:'.implode('|', UserRole::administrative()))->group(function (): void {
        // Zona audio
        Route::resource('zona', AudioZoneController::class)
            ->parameters(['zona' => 'audioZone'])
            ->names('audio-zones');

        Route::put('zona/{audioZone}/pengaturan-adzan', [AudioZoneController::class, 'updatePrayerSettings'])
            ->name('audio-zones.prayer-settings.update');
        Route::patch('zona/{audioZone}/status', [AudioZoneController::class, 'toggle'])
            ->name('audio-zones.toggle');
        Route::post('zona/{audioZone}/perintah', [AudioZoneController::class, 'dispatchCommand'])
            ->name('audio-zones.commands.store');

        // Audio
        Route::resource('audio', AudioTrackController::class)
            ->parameters(['audio' => 'audioTrack'])
            ->names('audio-tracks');
        Route::patch('audio/{audioTrack}/default', [AudioTrackController::class, 'markDefault'])
            ->name('audio-tracks.default');

        // Jadwal murottal / tilawah
        Route::resource('murottal', MurottalScheduleController::class)
            ->parameters(['murottal' => 'murottalSchedule'])
            ->names('murottal-schedules');
        Route::patch('murottal/{murottalSchedule}/status', [MurottalScheduleController::class, 'toggle'])
            ->name('murottal-schedules.toggle');

        // Perangkat player
        Route::resource('perangkat', DeviceController::class)
            ->parameters(['perangkat' => 'device'])
            ->names('devices');
        Route::post('perangkat/{device}/token', [DeviceController::class, 'rotateToken'])
            ->name('devices.token.rotate');
        Route::post('perangkat/{device}/perintah', [DeviceController::class, 'dispatchCommand'])
            ->name('devices.commands.store');

        // Monitoring pemutaran
        Route::get('monitoring', [PlaybackLogController::class, 'index'])->name('playback-logs.index');
        Route::patch('monitoring/{playbackLog}/tindak-lanjut', [PlaybackLogController::class, 'acknowledge'])
            ->name('playback-logs.acknowledge');
        Route::post('monitoring/tindak-lanjut-semua', [PlaybackLogController::class, 'acknowledgeAll'])
            ->name('playback-logs.acknowledge-all');

        // Jadwal sholat — pengelolaan
        Route::put('jadwal-sholat/{date}', [PrayerScheduleController::class, 'update'])
            ->name('prayer-schedules.update');
        Route::delete('jadwal-sholat/{date}', [PrayerScheduleController::class, 'reset'])
            ->name('prayer-schedules.reset');
        Route::post('jadwal-sholat/generate', [PrayerScheduleController::class, 'generate'])
            ->name('prayer-schedules.generate');

        // Jadwal Jum'at — pengelolaan
        Route::resource('jadwal-jumat', FridayScheduleController::class)
            ->except(['index'])
            ->parameters(['jadwal-jumat' => 'fridaySchedule'])
            ->names('friday-schedules');

        // Petugas sholat Dzuhur & Ashar — imam dan muadzin
        Route::get('petugas-sholat', [PrayerDutyController::class, 'index'])->name('prayer-duties.index');
        Route::get('petugas-sholat/cetak', [PrayerDutyController::class, 'print'])->name('prayer-duties.print');
        Route::get('petugas-sholat/cetak/pdf', [PrayerDutyController::class, 'pdf'])->name('prayer-duties.pdf');
        Route::put('petugas-sholat', [PrayerDutyController::class, 'update'])->name('prayer-duties.update');

        // Pengguna
        Route::resource('pengguna', UserController::class)
            ->parameters(['pengguna' => 'user'])
            ->names('users');
        Route::patch('pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');
        Route::put('pengguna/{user}/password', [UserController::class, 'resetPassword'])
            ->name('users.password.update');

        // Pengaturan
        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');

        // Aligns the local calculation with the published Kemenag schedule.
        Route::get('pengaturan/kalibrasi/kota', [PrayerCalibrationController::class, 'searchCities'])
            ->name('calibration.cities');
        Route::get('pengaturan/kalibrasi/pratinjau', [PrayerCalibrationController::class, 'preview'])
            ->name('calibration.preview');
        Route::post('pengaturan/kalibrasi', [PrayerCalibrationController::class, 'apply'])
            ->name('calibration.apply');
        // Live preview of the schedule for values still being typed.
        Route::get('pengaturan/pratinjau', [SettingController::class, 'preview'])
            ->name('settings.preview');
        Route::get('pengaturan/alamat', [PrayerCalibrationController::class, 'describeLocation'])
            ->name('settings.describe-location');
        Route::get('log-aktivitas', [SettingController::class, 'activityLog'])->name('settings.activity-log');
    });
});
