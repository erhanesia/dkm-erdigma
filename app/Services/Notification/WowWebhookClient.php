<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;

/**
 * Posts a message to WOW, which forwards it to the WhatsApp group.
 *
 * DKM never talks to WhatsApp itself. WOW already holds the session, the group
 * and whatever rate limits WhatsApp imposes, so duplicating any of that here
 * would mean two systems to keep logged in.
 *
 * The payload shape is assembled from configuration rather than hard-coded,
 * because WOW's contract was not available when this was written. Adjusting to
 * it is a change to `.env`, not to this class.
 */
class WowWebhookClient
{
    /**
     * @return array{ok: bool, status: int|null, body: string|null, error: string|null, payload: array<string, mixed>}
     */
    public function send(string $message): array
    {
        $payload = $this->payloadFor($message);

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'WOW belum dikonfigurasi (WOW_ENABLED / WOW_WEBHOOK_URL kosong).',
                'payload' => $payload,
            ];
        }

        try {
            $response = Http::timeout((int) config('dkm.whatsapp.timeout'))
                ->withHeaders($this->headers())
                ->acceptJson()
                ->asJson()
                ->post((string) config('dkm.whatsapp.webhook_url'), $payload);
        } catch (\Throwable $e) {
            // A network failure is not a bug in the message; the caller records
            // it and the same row can be retried.
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ];
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 2000),
            'error' => $response->successful() ? null : 'WOW menjawab HTTP '.$response->status(),
            'payload' => $payload,
        ];
    }

    /**
     * Whether there is somewhere to send to.
     */
    public function isConfigured(): bool
    {
        return (bool) config('dkm.whatsapp.enabled')
            && filled(config('dkm.whatsapp.webhook_url'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $message): array
    {
        /** @var array<string, string> $map */
        $map = config('dkm.whatsapp.field_map');

        return [
            $map['target'] => config('dkm.whatsapp.target'),
            $map['message'] => $message,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $token = config('dkm.whatsapp.token');

        if (blank($token)) {
            return [];
        }

        return [
            (string) config('dkm.whatsapp.auth_header') => config('dkm.whatsapp.auth_prefix').$token,
        ];
    }
}
