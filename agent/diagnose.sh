#!/usr/bin/env bash
#
# Reads what the DKM server needs to know about the audio PC.
#
# The one thing that cannot be decided from here is whether each room can be
# controlled separately. That depends on how many audio outputs this machine has
# and how the speaker wiring uses them — so this script reports the outputs and
# leaves the conclusion to whoever knows the cabling.
#
# Read-only: it starts nothing, installs nothing, and changes no setting.
#
# Usage:  bash diagnose.sh          (no root needed; a few checks say more with sudo)

set -uo pipefail

section() { printf '\n\033[1;36m== %s ==\033[0m\n' "$1"; }
item()    { printf '  %-26s %s\n' "$1" "$2"; }
warn()    { printf '  \033[1;33m! %s\033[0m\n' "$1"; }
have()    { command -v "$1" >/dev/null 2>&1; }

printf '\033[1mDiagnosa PC Audio — DKM Erdigma\033[0m\n'
printf 'Dijalankan %s di %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$(hostname)"

# -----------------------------------------------------------------------------
section "1. Sistem"

if have lsb_release; then
    item "Distribusi" "$(lsb_release -ds)"
else
    item "Distribusi" "$(awk -F= '/^PRETTY_NAME=/{gsub(/"/,"",$2); print $2}' /etc/os-release 2>/dev/null || echo 'tidak terbaca')"
fi

item "Kernel / arsitektur" "$(uname -r) / $(uname -m)"
item "Menyala sejak"       "$(uptime -p 2>/dev/null || echo 'tidak terbaca')"
item "RAM"                 "$(free -h 2>/dev/null | awk '/^Mem:/{print $2" (terpakai "$3")"}' || echo '?')"

# A CLI-only install cannot run a browser, which decides whether the player has
# to be a background service instead of a web page.
default_target="$(systemctl get-default 2>/dev/null || echo '?')"
item "Target default" "$default_target"

case "$default_target" in
    graphical.target)  item "Mode" "ada desktop — browser mungkin bisa dipakai" ;;
    multi-user.target) item "Mode" "CLI saja — tidak ada desktop, browser tidak bisa dipakai" ;;
    *)                 item "Mode" "tidak terbaca" ;;
esac

# -----------------------------------------------------------------------------
section "2. Keluaran audio  << INI YANG PALING PENTING >>"

echo "  Setiap 'card' di bawah adalah satu jalur suara yang bisa dikendalikan"
echo "  terpisah. Kalau hanya ada SATU, semua ruangan pasti dengar hal yang sama."
echo

if have aplay; then
    echo "  --- aplay -l ---"
    aplay -l 2>/dev/null | sed 's/^/  /' || warn "aplay tidak mengembalikan apa pun"

    card_count="$(aplay -l 2>/dev/null | grep -c '^card ' || true)"
    card_count="${card_count:-0}"
    echo
    item "Jumlah keluaran" "$card_count"

    if [ "$card_count" -le 1 ]; then
        warn "Hanya satu keluaran: semua ruangan berbagi satu jalur suara."
        warn "Mematikan tilawah di satu ruangan saja TIDAK bisa lewat software."
    else
        item "Kesimpulan" "lebih dari satu keluaran — ruangan berpotensi dipisah"
    fi
else
    warn "aplay tidak ada (paket alsa-utils belum terpasang)"
fi

echo
if have pactl && pactl info >/dev/null 2>&1; then
    item "Server suara" "$(pactl info 2>/dev/null | awk -F': ' '/Server Name/{print $2}')"
    echo "  --- sink (tujuan suara) ---"
    pactl list short sinks 2>/dev/null | sed 's/^/  /' || warn "tidak ada sink"
    echo "  --- sink yang sedang dipakai ---"
    pactl get-default-sink 2>/dev/null | sed 's/^/  /'
else
    item "PulseAudio/PipeWire" "tidak berjalan (audio langsung lewat ALSA)"
fi

echo
if have amixer; then
    echo "  --- level volume ---"
    amixer -M 2>/dev/null | grep -E 'Simple mixer|Front Left:|\[on\]|\[off\]' | head -12 | sed 's/^/  /'
fi

# -----------------------------------------------------------------------------
section "3. Apa yang memutar adzan sekarang"

echo "  Perlu diketahui supaya penggantinya tidak bentrok dengan yang lama."
echo

found_existing=0

echo "  --- layanan systemd yang mencurigakan ---"
matches="$(systemctl list-units --type=service --all --no-legend --no-pager 2>/dev/null \
    | grep -iE 'adzan|adhan|shol|shal|pray|murot|tilawah|audio|mpd|mopidy|dkm' || true)"
if [ -n "$matches" ]; then
    echo "$matches" | sed 's/^/  /'
    found_existing=1
else
    echo "  (tidak ada)"
fi

echo
echo "  --- crontab ---"
user_cron="$(crontab -l 2>/dev/null | grep -vE '^[[:space:]]*#|^[[:space:]]*$' || true)"
if [ -n "$user_cron" ]; then
    echo "$user_cron" | sed 's/^/  /'
    found_existing=1
else
    echo "  (crontab user kosong)"
fi

root_cron="$(sudo -n crontab -l 2>/dev/null | grep -vE '^[[:space:]]*#|^[[:space:]]*$' || true)"
if [ -n "$root_cron" ]; then
    echo "  --- crontab root ---"
    echo "$root_cron" | sed 's/^/  /'
    found_existing=1
fi

for dir in /etc/cron.d /etc/cron.daily /etc/cron.hourly; do
    entries="$(ls -1 "$dir" 2>/dev/null | grep -vE '^\.|placeholder' || true)"
    if [ -n "$entries" ]; then
        echo "  --- $dir ---"
        echo "$entries" | sed 's/^/  /'
        found_existing=1
    fi
done

echo
echo "  --- proses yang sedang memutar suara ---"
playing="$(ps -eo pid,etime,cmd --no-headers 2>/dev/null \
    | grep -iE 'mpv|mplayer|ffplay|mpg123|mpg321|aplay|paplay|vlc|cvlc|sox' \
    | grep -v grep || true)"
if [ -n "$playing" ]; then
    echo "$playing" | sed 's/^/  /'
    found_existing=1
else
    echo "  (tidak ada yang sedang memutar)"
fi

echo
if [ "$found_existing" -eq 1 ]; then
    item "Kesimpulan" "ada mekanisme lama — perlu dimatikan saat pindah ke DKM"
else
    item "Kesimpulan" "tidak ketemu otomatis; tanya siapa yang memasang skripnya"
fi

# -----------------------------------------------------------------------------
section "4. Pemutar audio yang tersedia"

echo "  Agent DKM butuh salah satu dari ini. mpv paling disukai: ia bisa"
echo "  dikendalikan saat berjalan (stop, ubah volume) tanpa dibunuh paksa."
echo

for player in mpv ffplay mpg123 paplay aplay sox vlc; do
    if have "$player"; then
        item "$player" "ADA — $(command -v "$player")"
    else
        item "$player" "tidak ada"
    fi
done

echo
for tool in curl jq systemctl; do
    if have "$tool"; then
        item "$tool" "ADA"
    else
        item "$tool" "tidak ada (perlu dipasang)"
    fi
done

# -----------------------------------------------------------------------------
section "5. Jaringan"

item "Alamat IP" "$(hostname -I 2>/dev/null | tr -s ' ' | sed 's/ $//' || echo '?')"

if have curl; then
    for target in https://dkm.erdigma.id https://api.myquran.com; do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "$target" 2>/dev/null || echo '000')"
        if [ "$code" = "000" ]; then
            item "$target" "TIDAK TERJANGKAU"
        else
            item "$target" "terjangkau (HTTP $code)"
        fi
    done
else
    warn "curl belum terpasang, koneksi tidak bisa diuji"
fi

# -----------------------------------------------------------------------------
section "6. Perilaku saat listrik mati"

echo "  Agent DKM akan dipasang sebagai layanan systemd, jadi ia hidup lagi"
echo "  sendiri setiap kali OS booting. Yang tidak bisa diatur dari software"
echo "  adalah apakah PC-nya sendiri mau menyala setelah listrik kembali —"
echo "  itu satu setelan di BIOS."
echo

item "Setelan BIOS" "cari 'Restore on AC Power Loss' lalu set ke 'Power On'"

echo
echo "  --- riwayat boot terakhir ---"
(last reboot 2>/dev/null | head -5 || journalctl --list-boots 2>/dev/null | tail -5) | sed 's/^/  /'

printf '\n\033[1;32mSelesai.\033[0m Simpan seluruh keluaran ini dan kirimkan.\n'
printf 'Bagian 2 yang paling menentukan: berapa banyak keluaran audio yang ada.\n\n'
