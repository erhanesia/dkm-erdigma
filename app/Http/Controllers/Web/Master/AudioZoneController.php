<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Master;

use App\Enums\AudioTrackType;
use App\Enums\DeviceCommandType;
use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Http\Requests\AudioZone\StoreAudioZoneRequest;
use App\Http\Requests\AudioZone\UpdateAudioZoneRequest;
use App\Http\Requests\AudioZone\UpdateZonePrayerSettingsRequest;
use App\Models\AudioZone;
use App\Models\User;
use App\Services\Audio\AudioTrackService;
use App\Services\Audio\AudioZoneService;
use App\Services\Device\DeviceCommandService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rooms with speakers. Everything about who hears what is configured here.
 */
class AudioZoneController extends Controller
{
    public function __construct(
        private readonly AudioZoneService $zones,
        private readonly AudioTrackService $tracks,
        private readonly DeviceCommandService $commands,
    ) {}

    public function index(): View
    {
        return view('pages.audio-zones.index', [
            'zones' => $this->zones->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('pages.audio-zones.create');
    }

    public function store(StoreAudioZoneRequest $request): RedirectResponse
    {
        $zone = $this->zones->create($request->validated());

        Flash::success('Zona "'.$zone->name.'" berhasil dibuat beserta pengaturan adzan bawaannya.');

        return redirect()->route('audio-zones.show', $zone);
    }

    public function show(AudioZone $audioZone): View
    {
        $audioZone->load([
            'devices',
            'murottalSchedules.track',
            'prayerSettings.adhanTrack',
            'prayerSettings.tarhimTrack',
            'prayerSettings.iqamahTrack',
        ]);

        return view('pages.audio-zones.show', [
            'zone' => $audioZone,
            'prayers' => PrayerName::withAdhan(),
            'settings' => $audioZone->prayerSettings->keyBy(
                static fn ($setting): string => $setting->prayer->value,
            ),
            'adhanTracks' => $this->tracks->optionsForTypes(...AudioTrackType::adhanTypes()),
            'tarhimTracks' => $this->tracks->optionsForTypes(AudioTrackType::Tarhim),
            'iqamahTracks' => $this->tracks->optionsForTypes(AudioTrackType::Iqamah),
            'commandTypes' => DeviceCommandType::options(),
        ]);
    }

    public function edit(AudioZone $audioZone): View
    {
        return view('pages.audio-zones.edit', ['zone' => $audioZone]);
    }

    public function update(UpdateAudioZoneRequest $request, AudioZone $audioZone): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->zones->update($audioZone, $request->validated(), $actor);

        Flash::success('Zona berhasil diperbarui dan perangkat di ruangan ini sudah diminta memuat ulang jadwal.');

        return redirect()->route('audio-zones.show', $audioZone);
    }

    public function destroy(AudioZone $audioZone): RedirectResponse
    {
        $this->zones->delete($audioZone);

        Flash::success('Zona berhasil dihapus.');

        return redirect()->route('audio-zones.index');
    }

    /**
     * Saves the per-prayer adhan grid for this room.
     */
    public function updatePrayerSettings(UpdateZonePrayerSettingsRequest $request, AudioZone $audioZone): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->zones->savePrayerSettings($audioZone, $request->settingsByPrayer(), $actor);

        Flash::success('Pengaturan adzan untuk zona '.$audioZone->name.' berhasil disimpan.');

        return redirect()->route('audio-zones.show', $audioZone);
    }

    /**
     * The quick switches: aktif/nonaktif, adzan on/off, tilawah on/off.
     */
    public function toggle(Request $request, AudioZone $audioZone): RedirectResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'in:is_active,is_adhan_enabled,is_murottal_enabled'],
            'value' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $value = (bool) $validated['value'];

        $message = match ($validated['field']) {
            'is_active' => $value
                ? 'Zona diaktifkan.'
                : 'Zona dinonaktifkan dan audio di ruangan ini dihentikan.',
            'is_adhan_enabled' => $value
                ? 'Adzan dinyalakan untuk zona ini.'
                : 'Adzan dimatikan untuk zona ini.',
            default => $value
                ? 'Tilawah dinyalakan untuk zona ini.'
                : 'Tilawah dimatikan dan audio yang sedang berjalan dihentikan.',
        };

        match ($validated['field']) {
            'is_active' => $this->zones->toggleActive($audioZone, $value, $actor),
            'is_adhan_enabled' => $this->zones->toggleAdhan($audioZone, $value, $actor),
            default => $this->zones->toggleMurottal($audioZone, $value, $actor),
        };

        Flash::success($message);

        return back();
    }

    /**
     * Sends one instruction to every player in the room — test the speakers,
     * stop the audio, change the volume.
     */
    public function dispatchCommand(Request $request, AudioZone $audioZone): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', DeviceCommandType::values())],
            'payload' => ['nullable', 'array'],
            'payload.volume' => ['nullable', 'integer', 'between:0,100'],
            'payload.audio_track_id' => ['nullable', 'integer', 'exists:audio_tracks,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $type = DeviceCommandType::from($validated['type']);

        $reached = $this->commands->dispatchToZone(
            $audioZone,
            $type,
            $validated['payload'] ?? [],
            $actor,
        );

        if ($reached === 0) {
            Flash::warning('Tidak ada perangkat aktif di zona ini, jadi perintah tidak terkirim.');

            return back();
        }

        Flash::success('Perintah "'.$type->label().'" dikirim ke '.$reached.' perangkat.');

        return back();
    }
}
