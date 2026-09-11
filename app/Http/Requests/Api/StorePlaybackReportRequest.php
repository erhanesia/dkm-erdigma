<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\PlaybackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of `POST /api/v1/devices/me/playbacks`.
 *
 * Accepts a batch, because a player that was offline catches up by sending
 * everything it buffered at once.
 */
class StorePlaybackReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reports' => ['required', 'array', 'min:1', 'max:50'],
            'reports.*.reference_key' => ['required', 'string', 'max:120'],
            'reports.*.status' => ['required', Rule::in(PlaybackStatus::reportableValues())],
            'reports.*.started_at' => ['nullable', 'date'],
            'reports.*.finished_at' => ['nullable', 'date', 'after_or_equal:reports.*.started_at'],
            // Measured output levels — the evidence that sound actually left the speakers.
            'reports.*.peak_audio_level' => ['nullable', 'numeric', 'between:0,1'],
            'reports.*.average_audio_level' => ['nullable', 'numeric', 'between:0,1'],
            'reports.*.volume' => ['nullable', 'integer', 'between:0,100'],
            'reports.*.failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reports(): array
    {
        /** @var array<int, array<string, mixed>> $reports */
        $reports = $this->validated('reports');

        return $reports;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reports.required' => 'Laporan pemutaran tidak boleh kosong.',
            'reports.max' => 'Maksimal 50 laporan dalam satu pengiriman.',
            'reports.*.status.in' => 'Status pemutaran tidak dikenali.',
        ];
    }
}
