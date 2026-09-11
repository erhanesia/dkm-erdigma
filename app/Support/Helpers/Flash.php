<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Http\RedirectResponse;

/**
 * Flash message helper shared by every web controller.
 *
 * The stored payload is read once by `partials.flash`, which renders it as a
 * toast. Keeping the keys in one place means the Blade partial never has to
 * guess which session key a controller decided to use.
 */
final class Flash
{
    private const SESSION_KEY = 'flash_notification';

    public static function success(string $message, ?string $title = 'Berhasil'): void
    {
        self::push('success', $message, $title);
    }

    public static function error(string $message, ?string $title = 'Gagal'): void
    {
        self::push('danger', $message, $title);
    }

    public static function warning(string $message, ?string $title = 'Perhatian'): void
    {
        self::push('warning', $message, $title);
    }

    public static function info(string $message, ?string $title = 'Informasi'): void
    {
        self::push('info', $message, $title);
    }

    /**
     * Flash a success message and redirect in a single expression.
     */
    public static function redirectSuccess(RedirectResponse $redirect, string $message, ?string $title = 'Berhasil'): RedirectResponse
    {
        self::success($message, $title);

        return $redirect;
    }

    /**
     * @return array{type: string, title: ?string, message: string}|null
     */
    public static function pull(): ?array
    {
        /** @var array{type: string, title: ?string, message: string}|null $payload */
        $payload = session()->pull(self::SESSION_KEY);

        return $payload;
    }

    private static function push(string $type, string $message, ?string $title): void
    {
        session()->flash(self::SESSION_KEY, [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }
}
