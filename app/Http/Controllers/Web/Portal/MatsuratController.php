<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Al-Ma'tsurat, on the public site.
 *
 * Needs no account, like the Qur'an page: it is useful to anyone who opens it,
 * not only to the office.
 *
 * Thin on purpose. Which recension and which time are shown belongs to the
 * component below, because those are the things that change without a new
 * request — putting them here would mean two places deciding the same thing.
 */
class MatsuratController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.portal.matsurat');
    }
}
