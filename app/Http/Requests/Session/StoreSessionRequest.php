<?php

declare(strict_types=1);

namespace App\Http\Requests\Session;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Services\AfterHours\SessionSeriesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One after-hours mentoring meeting — or the first of a series of them, when
 * "Kegiatan berulang" is switched on.
 */
class StoreSessionRequest extends FormRequest
{
    /**
     * Administrators may schedule for any halaqah; a mentor only for their own.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(UserRole::administrative())) {
            return true;
        }

        $groupId = (int) $this->input('mentoring_group_id');

        return MentoringGroup::query()
            ->whereKey($groupId)
            ->where('mentor_id', $user->id)
            ->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mentoring_group_id' => ['required', 'integer', 'exists:mentoring_groups,id'],
            'topic' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(SessionStatus::values())],
            'is_qr_enabled' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'is_recurring' => ['boolean'],
            'repeat_every_weeks' => ['exclude_unless:is_recurring,true', 'required', 'integer', Rule::in(SessionSeriesService::INTERVALS)],
            'repeat_until' => ['exclude_unless:is_recurring,true', 'required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_qr_enabled' => $this->boolean('is_qr_enabled'),
            'is_public' => $this->boolean('is_public'),
            'is_recurring' => $this->boolean('is_recurring'),
        ]);
    }

    /**
     * A series repeats one time slot, so each meeting has to fit inside a
     * single day, and the series may run for at most six months.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isRecurring() || $validator->errors()->hasAny(['starts_at', 'ends_at', 'repeat_until'])) {
                    return;
                }

                $startsAt = CarbonImmutable::parse($this->string('starts_at')->toString());
                $endsAt = CarbonImmutable::parse($this->string('ends_at')->toString());
                $until = CarbonImmutable::parse($this->string('repeat_until')->toString())->startOfDay();

                if (! $endsAt->isSameDay($startsAt)) {
                    $validator->errors()->add('ends_at', 'Kegiatan berulang harus selesai di hari yang sama dengan mulainya.');
                }

                if ($until->lessThan($startsAt->startOfDay())) {
                    $validator->errors()->add('repeat_until', 'Tanggal akhir pengulangan tidak boleh sebelum kegiatan pertama.');
                } elseif ($until->greaterThan(SessionSeriesService::latestEndFor($startsAt))) {
                    $validator->errors()->add(
                        'repeat_until',
                        'Kegiatan berulang paling lama '.SessionSeriesService::MAX_MONTHS.' bulan dari kegiatan pertama.',
                    );
                }
            },
        ];
    }

    public function isRecurring(): bool
    {
        return $this->boolean('is_recurring');
    }

    /**
     * The session itself, without the fields that only say how it repeats —
     * those are not columns, and the model refuses attributes it cannot store.
     *
     * @return array<string, mixed>
     */
    public function sessionAttributes(): array
    {
        return collect($this->validated())->except(['is_recurring', 'repeat_every_weeks', 'repeat_until'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'repeat_every_weeks.in' => 'Pilih setiap minggu atau setiap 2 minggu.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mentoring_group_id' => 'halaqah',
            'topic' => 'materi kegiatan',
            'starts_at' => 'waktu mulai',
            'ends_at' => 'waktu selesai',
            'location' => 'tempat',
            'repeat_every_weeks' => 'pengulangan',
            'repeat_until' => 'tanggal akhir pengulangan',
            'is_public' => 'tampilkan di halaman publik',
        ];
    }
}
