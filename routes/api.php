<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AudioTrackStreamController;
use App\Http\Controllers\Api\V1\DeviceCommandController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DeviceHeartbeatController;
use App\Http\Controllers\Api\V1\DevicePlaybackController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaybackPlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API — v1
|--------------------------------------------------------------------------
|
| Consumed by the in-room browser player. Resources are plural nouns and the
| HTTP method carries the intent; `me` is the singleton standing for "the device
| holding this token", so a device can never address another device.
|
| Authentication is a bearer token issued when the device is registered, and
| every route is rate limited per token.
|
*/

/*
 * The signed-in person.
 *
 * `me` is singular on purpose: it names whoever holds the current session, and
 * accepts no id, so a caller can never address anyone but themselves. Same
 * reasoning as `devices/me` below.
 *
 * Authentication is Sanctum, which accepts either the browser session cookie or
 * a bearer token — so this one route serves the web app and any future client.
 */
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::get('me', [MeController::class, 'show'])->name('me.show');
    });

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['device', 'throttle:device'])
    ->group(function (): void {
        Route::prefix('devices/me')->name('devices.me.')->group(function (): void {
            Route::get('/', [DeviceController::class, 'show'])->name('show');

            Route::get('playback-plans', [PlaybackPlanController::class, 'index'])->name('playback-plans.index');

            Route::post('heartbeats', [DeviceHeartbeatController::class, 'store'])->name('heartbeats.store');

            Route::get('commands', [DeviceCommandController::class, 'index'])->name('commands.index');
            Route::patch('commands/{command}', [DeviceCommandController::class, 'update'])->name('commands.update');

            Route::post('playbacks', [DevicePlaybackController::class, 'store'])->name('playbacks.store');
        });

        Route::get('audio-tracks/{audioTrack}/content', AudioTrackStreamController::class)
            ->name('audio-tracks.content');
    });
