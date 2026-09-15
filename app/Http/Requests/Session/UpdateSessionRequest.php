<?php

declare(strict_types=1);

namespace App\Http\Requests\Session;

use Illuminate\Support\Arr;

/**
 * Changing a session that already exists.
 *
 * The time is not among its fields: moving a session goes through Jadwal
 * Ulang, which records the time it was first set for. A time edited here would
 * move it without leaving that trace. Repetition belongs to scheduling, not to
 * editing one meeting.
 */
class UpdateSessionRequest extends StoreSessionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['starts_at', 'ends_at', 'is_recurring', 'repeat_every_weeks', 'repeat_until']);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [];
    }
}
