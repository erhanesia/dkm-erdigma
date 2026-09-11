<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Master;

use App\Enums\AudioTrackType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AudioTrack\StoreAudioTrackRequest;
use App\Models\AudioTrack;
use App\Models\User;
use App\Services\Audio\AudioTrackService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The library of adhan, tarhim, iqamah, murottal, and speaker-test audio.
 */
class AudioTrackController extends Controller
{
    public function __construct(
        private readonly AudioTrackService $tracks,
    ) {}

    public function index(): View
    {
        return view('pages.audio-tracks.index', [
            'tracks' => $this->tracks->paginate(),
            'types' => AudioTrackType::options(),
        ]);
    }

    public function create(): View
    {
        return view('pages.audio-tracks.create', [
            'types' => AudioTrackType::options(),
        ]);
    }

    public function store(StoreAudioTrackRequest $request): RedirectResponse
    {
        /** @var User $uploader */
        $uploader = $request->user();

        $track = $this->tracks->create(
            $request->trackAttributes(),
            $request->file('file'),
            $uploader,
        );

        Flash::success('Audio "'.$track->title.'" berhasil diunggah.');

        return redirect()->route('audio-tracks.index');
    }

    public function show(AudioTrack $audioTrack): View
    {
        return view('pages.audio-tracks.show', [
            'track' => $audioTrack->load('uploader'),
        ]);
    }

    public function edit(AudioTrack $audioTrack): View
    {
        return view('pages.audio-tracks.edit', [
            'track' => $audioTrack,
            'types' => AudioTrackType::options(),
        ]);
    }

    public function update(StoreAudioTrackRequest $request, AudioTrack $audioTrack): RedirectResponse
    {
        $this->tracks->update(
            $audioTrack,
            $request->trackAttributes(),
            $request->file('file'),
        );

        Flash::success('Audio berhasil diperbarui.');

        return redirect()->route('audio-tracks.index');
    }

    public function destroy(AudioTrack $audioTrack): RedirectResponse
    {
        $this->tracks->delete($audioTrack);

        Flash::success('Audio berhasil dihapus.');

        return redirect()->route('audio-tracks.index');
    }

    /**
     * Marks this track as the fallback used whenever a zone has not picked one.
     */
    public function markDefault(AudioTrack $audioTrack): RedirectResponse
    {
        $this->tracks->markAsDefault($audioTrack);

        Flash::success('"'.$audioTrack->title.'" kini menjadi audio default untuk '
            .$audioTrack->type->label().'.');

        return back();
    }
}
