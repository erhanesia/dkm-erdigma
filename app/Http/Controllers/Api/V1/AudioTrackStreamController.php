<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AudioTrack;
use App\Services\Audio\AudioTrackService;
use App\Support\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /api/v1/audio-tracks/{audioTrack}/content`
 *
 * Streams the raw audio to an authenticated player, which decodes it once and
 * keeps the bytes in IndexedDB so later playbacks survive a network outage.
 *
 * Files live on a private disk, so this endpoint is the only way to reach them —
 * the library cannot be enumerated by guessing storage URLs.
 */
class AudioTrackStreamController extends Controller
{
    public function __construct(
        private readonly AudioTrackService $tracks,
    ) {}

    public function __invoke(Request $request, AudioTrack $audioTrack): StreamedResponse|Response
    {
        if (! $audioTrack->is_active) {
            return ApiResponse::notFound('Audio ini sudah tidak aktif.');
        }

        $stream = $this->tracks->readStream($audioTrack);

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            Response::HTTP_OK,
            [
                'Content-Type' => $audioTrack->mime_type ?? 'audio/mpeg',
                'Content-Length' => (string) $this->tracks->fileSize($audioTrack),
                'Content-Disposition' => 'inline; filename="'.addslashes($audioTrack->title).'"',
                // The library rarely changes and players re-fetch on plan reload.
                'Cache-Control' => 'private, max-age=86400',
                'Accept-Ranges' => 'none',
            ],
        );
    }
}
