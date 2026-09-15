<?php

declare(strict_types=1);

namespace App\Http\Requests\Session;

use App\Models\AfterHoursSession;
use App\Models\User;
use App\Services\AfterHours\AfterHoursSessionService;
use Illuminate\Support\Arr;

/**
 * Changing a session that already exists.
 *
 * The time is not among its fields: moving a session goes through Jadwal
 * Ulang, which records the time it was first set for. A time edited here would
 * move it without leaving that trace. Repetition belongs to scheduling, not to
 * editing one meeting.
 *
 * Once a session is completed, only its status and last reading are asked for.
 * The form sends the rest disabled, so it has nothing else to validate.
 */
class UpdateSessionRequest extends StoreSessionRequest
{
    /**
     * A completed session's form sends no halaqah to check against, so the
     * session's own halaqah decides instead.
     */
    public function authorize(): bool
    {
        if (! $this->isCompletedSession()) {
            return parent::authorize();
        }

        /** @var User|null $user */
        $user = $this->user();

        return $user !== null
            && ($user->isAdministrator() || $this->editedSession()->group->mentor_id === $user->id);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = Arr::except(parent::rules(), ['starts_at', 'ends_at', 'is_recurring', 'repeat_every_weeks', 'repeat_until']);

        return $this->isCompletedSession()
            ? Arr::only($rules, AfterHoursSessionService::EDITABLE_ONCE_COMPLETED)
            : $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), 'summary' => 'bacaan terakhir'];
    }

    private function isCompletedSession(): bool
    {
        return ! $this->editedSession()->status->isEditable();
    }

    private function editedSession(): AfterHoursSession
    {
        /** @var AfterHoursSession */
        return $this->route('afterHoursSession');
    }
}
