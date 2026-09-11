APP_NAME="DKM Erdigma"
APP_ENV=local
APP_KEY=base64:e1EZzilekAv0Uqis4thtba1QkfRYgD3qGjbrjlVOTfc=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=dkm_erdigma
DB_USERNAME=sail
DB_PASSWORD=password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

# ===================== DKM =====================
APP_TIMEZONE=Asia/Jakarta

# Lokasi masjid (dipakai untuk kalkulasi jadwal sholat)
DKM_MOSQUE_NAME="Musholla Erdigma"
# Musholla Erdigma, Blater, Purbalingga. Dicocokkan dengan catatan gedung
# "Erdigma Blater" di HRIS (-7.429184, 109.335308) — berjarak 76 m.
DKM_MOSQUE_ADDRESS="Blater, Purbalingga, Jawa Tengah, 53371"
DKM_LATITUDE=-7.429397
DKM_LONGITUDE=109.334649
DKM_ELEVATION=90
DKM_CALCULATION_METHOD=KEMENAG
DKM_ASR_METHOD=STANDARD

# Ambang batas perangkat dianggap offline (detik)
DKM_DEVICE_OFFLINE_THRESHOLD=180
# Ambang batas adzan dianggap terlewat (detik setelah jadwal)
DKM_PLAYBACK_MISSED_THRESHOLD=300
# Ambang level suara minimum agar playback dianggap terdengar (0..1)
DKM_MIN_AUDIO_LEVEL=0.01
# ==================== Docker / Laravel Sail ====================
# Port di HOST (laptop Anda). Sengaja bukan port bawaan supaya tidak
# bentrok dengan XAMPP yang memakai 3306 (MySQL) dan 80 (Apache).
APP_PORT=8000
VITE_PORT=5173
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025

# Diisi otomatis oleh skrip sail di Linux/macOS/WSL. Di Windows biarkan 1000.
WWWUSER=1000
WWWGROUP=1000
