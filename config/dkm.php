<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mosque Identity
    |--------------------------------------------------------------------------
    |
    | Default values used when the `settings` table has not been populated yet.
    | Everything here is overridable at runtime from the Settings page.
    |
    */

    'mosque' => [
        'name' => env('DKM_MOSQUE_NAME', 'Musholla Erdigma'),
        'address' => env('DKM_MOSQUE_ADDRESS', 'Blater, Purbalingga, Jawa Tengah, 53371'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prayer Time Calculation
    |--------------------------------------------------------------------------
    |
    | Coordinates and the calculation method fed into islamic-network/prayer-times.
    | `KEMENAG` matches the schedule published by the Indonesian Ministry of
    | Religious Affairs, which is what most Indonesian mosques follow.
    |
    */

    'prayer' => [
        /*
         * Musholla Erdigma, Blater, Purbalingga.
         *
         * Sempat berisi koordinat Bandung — placeholder yang dikarang di hari
         * pertama dan tidak pernah ditandai sebagai tebakan. Akibatnya jadwal
         * meleset 6 menit, dan setiap `migrate:fresh` diam-diam memindahkan
         * musholla 300 km sampai ada yang menyadarinya lagi.
         *
         * Angka ini dicocokkan dengan catatan gedung "Erdigma Blater" di HRIS
         * (-7.429184, 109.335308) — berjarak 76 m, persis skala musholla di
         * dalam kompleks kantor.
         *
         * Ketinggian hanya berpengaruh hitungan detik pada terbit dan maghrib,
         * jadi nilai perkiraan sudah memadai.
         */
        'latitude' => (float) env('DKM_LATITUDE', -7.429397),
        'longitude' => (float) env('DKM_LONGITUDE', 109.334649),
        'elevation' => (float) env('DKM_ELEVATION', 90),
        'timezone' => env('DKM_TIMEZONE', 'Asia/Jakarta'),
        'calculation_method' => env('DKM_CALCULATION_METHOD', 'KEMENAG'),
        'asr_method' => env('DKM_ASR_METHOD', 'STANDARD'),

        /*
         * Minute offsets applied on top of the calculated times (ihtiyati).
         * Keys must match \App\Enums\PrayerName values.
         */
        'adjustments' => [
            'fajr' => 2,
            'sunrise' => -2,
            'dhuhr' => 2,
            'asr' => 2,
            'maghrib' => 2,
            'isha' => 2,
        ],

        /*
         * How many days ahead the scheduler keeps pre-generated.
         */
        'generate_days_ahead' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Official Schedule (calibration only)
    |--------------------------------------------------------------------------
    |
    | The published Kemenag schedule, used once to measure how far the local
    | calculation sits from the official tables. It is never consulted to run
    | the adhan: the schedule is generated offline, so losing this service
    | costs nothing but the ability to re-calibrate.
    |
    */

    'official_schedule' => [
        /*
         * Kabupaten/kota yang jadwal resminya dipakai. 1420 = Kab. Purbalingga.
         * Dikosongkan berarti aplikasi hanya memakai perhitungan lokal.
         */
        'city_id' => env('DKM_OFFICIAL_CITY_ID', '1420'),

        'base_url' => env('DKM_OFFICIAL_SCHEDULE_URL', 'https://api.myquran.com/v2'),
        'timeout' => (int) env('DKM_OFFICIAL_SCHEDULE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverse Geocoding
    |--------------------------------------------------------------------------
    |
    | Turns the detected coordinates into a readable address on the Settings
    | page, so whoever sets the location can confirm the right place was picked.
    | Cosmetic only — the schedule is computed from the coordinates themselves,
    | so this service being unreachable changes nothing.
    |
    */

    'reverse_geocode' => [
        'base_url' => env('DKM_REVERSE_GEOCODE_URL', 'https://nominatim.openstreetmap.org'),
        'timeout' => (int) env('DKM_REVERSE_GEOCODE_TIMEOUT', 10),
        // Nominatim's usage policy requires an identifiable caller.
        'user_agent' => env('DKM_REVERSE_GEOCODE_UA', 'DKM-Erdigma/1.0 (dkm.erdigma.id)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Player & Device Monitoring
    |--------------------------------------------------------------------------
    |
    | These thresholds power the answer to "adzan sering tidak terdengar": the
    | browser player measures its own audio output and reports back, and the
    | server flags anything that never reported or reported silence.
    |
    */

    'device' => [
        // Seconds without a heartbeat before a device is considered offline.
        'offline_threshold' => (int) env('DKM_DEVICE_OFFLINE_THRESHOLD', 180),

        // How often the player asks the server for plan changes and commands.
        'poll_interval' => (int) env('DKM_DEVICE_POLL_INTERVAL', 15),

        // How often the player sends a heartbeat.
        'heartbeat_interval' => (int) env('DKM_DEVICE_HEARTBEAT_INTERVAL', 30),

        // Requests per minute allowed per device token.
        'rate_limit' => (int) env('DKM_DEVICE_RATE_LIMIT', 120),
    ],

    'playback' => [
        // Seconds after the scheduled time before an unconfirmed playback is MISSED.
        'missed_threshold' => (int) env('DKM_PLAYBACK_MISSED_THRESHOLD', 300),

        // Normalised RMS (0..1) below which audio is treated as inaudible.
        'min_audio_level' => (float) env('DKM_MIN_AUDIO_LEVEL', 0.01),

        // Daily speaker self-test time (24h, null disables it).
        'self_test_time' => env('DKM_SELF_TEST_TIME', '04:00'),

        // Days of playback history kept before pruning.
        'retention_days' => (int) env('DKM_PLAYBACK_RETENTION_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mentoring (After Hours)
    |--------------------------------------------------------------------------
    */

    'mentoring' => [
        // Default number of employees a single mentor (ustadz) handles.
        'default_group_capacity' => (int) env('DKM_GROUP_CAPACITY', 10),

        // Minutes before/after the session start that QR check-in stays open.
        'check_in_opens_before' => (int) env('DKM_CHECK_IN_OPENS_BEFORE', 30),
        'check_in_closes_after' => (int) env('DKM_CHECK_IN_CLOSES_AFTER', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audio Storage
    |--------------------------------------------------------------------------
    */

    'audio' => [
        'disk' => env('DKM_AUDIO_DISK', 'audio'),
        'max_size_kb' => (int) env('DKM_AUDIO_MAX_SIZE_KB', 51200),
        'allowed_mimes' => ['mp3', 'wav', 'ogg', 'm4a', 'mpga'],
        // Minutes a signed streaming URL stays valid for the player.
        'stream_url_ttl' => (int) env('DKM_AUDIO_STREAM_TTL', 720),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Al-Quran
    |--------------------------------------------------------------------------
    |
    | Teks, terjemahan, dan audio diambil dari equran.id. Isinya tidak pernah
    | berubah, jadi di-cache sangat lama — sesudah pemanggilan pertama, halaman
    | Quran tidak lagi bergantung pada API-nya sama sekali.
    |
    */
    'quran' => [
        'base_url' => env('QURAN_BASE_URL', 'https://equran.id/api/v2'),
        'timeout' => (int) env('QURAN_TIMEOUT', 15),

        // 30 hari. Mushaf tidak berubah; yang bisa berubah cuma perbaikan kecil
        // pada terjemahan, dan itu tidak mendesak.
        'cache_ttl' => (int) env('QURAN_CACHE_TTL', 2592000),

        // Kunci qari pada field `audio` milik equran.id.
        'default_reciter' => env('QURAN_RECITER', '05'),
        'reciters' => [
            '01' => 'Abdullah Al-Juhany',
            '02' => 'Abdul Muhsin Al-Qasim',
            '03' => 'Abdurrahman as-Sudais',
            '04' => 'Ibrahim Al-Dossari',
            '05' => 'Misyari Rasyid Al-Afasy',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp lewat WOW
    |--------------------------------------------------------------------------
    |
    | DKM tidak berbicara dengan WhatsApp secara langsung. Ia memanggil webhook
    | milik WOW — layanan internal Erdigma — dan WOW yang meneruskannya ke grup.
    |
    | Bentuk payload-nya dibuat bisa diatur karena kontrak WOW belum diketahui
    | saat ini ditulis. `field_map` menerjemahkan nama field kita ke nama yang
    | diharapkan WOW, jadi menyesuaikannya nanti cukup mengubah .env dan tidak
    | perlu menyentuh kode.
    |
    */
    'whatsapp' => [
        'enabled' => (bool) env('WOW_ENABLED', false),

        'webhook_url' => env('WOW_WEBHOOK_URL'),

        // Dikirim sebagai header Authorization bila diisi. Bentuknya menyusul
        // konfirmasi dari tim WOW.
        'token' => env('WOW_TOKEN'),
        'auth_header' => env('WOW_AUTH_HEADER', 'Authorization'),
        'auth_prefix' => env('WOW_AUTH_PREFIX', 'Bearer '),

        // Grup tujuan. Bisa berupa JID, nomor, atau nama — tergantung WOW.
        'target' => env('WOW_TARGET'),

        'timeout' => (int) env('WOW_TIMEOUT', 15),
        'max_attempts' => (int) env('WOW_MAX_ATTEMPTS', 3),

        // Nama field pada payload JSON yang dikirim ke WOW.
        'field_map' => [
            'target' => env('WOW_FIELD_TARGET', 'target'),
            'message' => env('WOW_FIELD_MESSAGE', 'message'),
        ],

        'announcement' => [
            // Berapa menit sebelum waktu sholat pesannya dikirim.
            'lead_minutes' => (int) env('WOW_LEAD_MINUTES', 5),

            // Toleransi keterlambatan scheduler. Kalau cron sempat tersendat,
            // pengingat yang sudah lewat lebih dari ini tidak lagi dikirim —
            // pengingat sholat yang datang setelah sholatnya cuma bikin bingung.
            'grace_minutes' => (int) env('WOW_GRACE_MINUTES', 3),

            // Sholat yang diumumkan. Terbit tidak termasuk: bukan waktu sholat.
            'prayers' => ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'],
        ],
    ],

    'per_page' => (int) env('DKM_PER_PAGE', 15),

];
