<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Services\Quran\QuranClient;
use Illuminate\View\View;

/**
 * The Qur'an, on the public site.
 *
 * Open to anyone without a login, which is the whole point — this is the one
 * part of the site that is useful to a passer-by rather than to the office.
 */
class QuranController extends Controller
{
    public function __construct(
        private readonly QuranClient $quran,
    ) {}

    /**
     * All 114 surahs.
     *
     * The whole list is sent at once and filtered in the browser: it is about
     * 6 KB of names, and a round trip per keystroke would be slower than the
     * filtering it replaced.
     */
    public function index(): View
    {
        return view('pages.portal.quran-index', [
            'surahs' => $this->quran->surahs(),
        ]);
    }

    public function show(int $surah): View
    {
        $found = $this->quran->surah($surah);

        abort_if($found === null, 404);

        return view('pages.portal.quran-surah', [
            'surah' => $found,
            'reciter' => $this->quran->reciters()[config('dkm.quran.default_reciter')] ?? null,
        ]);
    }
}
