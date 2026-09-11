<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Monitoring;

use App\Enums\DeviceCommandType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Device\StoreDeviceRequest;
use App\Models\Device;
use App\Models\User;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Services\Audio\AudioZoneService;
use App\Services\Device\DeviceCommandService;
use App\Services\Device\DeviceService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The browsers acting as room players: registration, pairing tokens, and health.
 */
class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $devices,
        private readonly DeviceRepositoryInterface $deviceRepository,
        private readonly DeviceCommandService $commands,
        private readonly AudioZoneService $zones,
        private readonly PlaybackLogRepositoryInterface $playbackLogs,
    ) {}

    public function index(): View
    {
        return view('pages.devices.index', [
            'devices' => $this->deviceRepository->paginateFiltered(),
            'zones' => $this->zones->options(),
            'health' => $this->devices->healthSummary(),
        ]);
    }

    public function create(): View
    {
        return view('pages.devices.create', [
            'zones' => $this->zones->options(),
        ]);
    }

    /**
     * Registering a device shows its token exactly once — only the hash is
     * stored, so it cannot be retrieved again afterwards.
     */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        ['device' => $device, 'token' => $token] = $this->devices->register($request->validated());

        Flash::success('Perangkat "'.$device->name.'" berhasil didaftarkan.');

        return redirect()
            ->route('devices.show', $device)
            ->with('device_token', $token);
    }

    public function show(Device $device): View
    {
        return view('pages.devices.show', [
            'device' => $device->load('zone'),
            'status' => $device->resolveStatus(),
            'commands' => $this->commands->historyFor($device),
            'commandTypes' => DeviceCommandType::options(),
            'recentPlaybacks' => $this->playbackLogs->all(
                ['device_id' => $device->id],
                ['track'],
            )->take(15),
            // Shown once, right after registration or a token rotation.
            'plainToken' => session('device_token'),
        ]);
    }

    public function edit(Device $device): View
    {
        return view('pages.devices.edit', [
            'device' => $device,
            'zones' => $this->zones->options(),
        ]);
    }

    public function update(StoreDeviceRequest $request, Device $device): RedirectResponse
    {
        $this->deviceRepository->update($device, $request->validated());

        Flash::success('Perangkat berhasil diperbarui.');

        return redirect()->route('devices.show', $device);
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->deviceRepository->delete($device);

        Flash::success('Perangkat berhasil dihapus.');

        return redirect()->route('devices.index');
    }

    /**
     * Issues a fresh secret; the old one stops working immediately.
     */
    public function rotateToken(Device $device): RedirectResponse
    {
        $token = $this->devices->rotateToken($device);

        Flash::warning('Token baru dibuat. Token lama sudah tidak berlaku — '
            .'buka ulang halaman player di perangkat ini dan masukkan token barunya.');

        return redirect()
            ->route('devices.show', $device)
            ->with('device_token', $token);
    }

    /**
     * Sends one instruction to this device — most usefully "Uji Speaker", which
     * plays a short tone and reports back the level it measured.
     */
    public function dispatchCommand(Request $request, Device $device): RedirectResponse
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

        $this->commands->dispatchTo($device, $type, $validated['payload'] ?? [], $actor);

        Flash::success('Perintah "'.$type->label().'" dikirim. '
            .'Perangkat akan menjalankannya pada pengecekan berikutnya.');

        return back();
    }
}
