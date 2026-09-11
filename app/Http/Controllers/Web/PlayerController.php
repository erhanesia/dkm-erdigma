<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\View\View;

/**
 * The page left open on the screen in each room.
 *
 * It carries no server-side session: the browser stores its device token in
 * `localStorage` and authenticates against the device API with it. That way a
 * kiosk machine never has to stay logged in as a person, and rebooting it after
 * a power cut brings the player straight back on its own.
 */
class PlayerController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
    ) {}

    public function show(): View
    {
        return view('pages.player.show', [
            'mosqueName' => $this->settings->mosqueName(),
            'pollInterval' => (int) config('dkm.device.poll_interval'),
            'heartbeatInterval' => (int) config('dkm.device.heartbeat_interval'),
            'minAudioLevel' => (float) config('dkm.playback.min_audio_level'),
        ]);
    }
}
