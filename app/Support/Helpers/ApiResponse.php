<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Uniform JSON envelope for every API endpoint, so the browser player and any
 * future client can rely on one shape instead of per-controller ad-hoc arrays.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, ?string $message = null, array $meta = [], int $status = HttpStatus::HTTP_OK): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(string $message, array $errors = [], int $status = HttpStatus::HTTP_BAD_REQUEST): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function notFound(string $message = 'Data tidak ditemukan.'): JsonResponse
    {
        return self::error($message, status: HttpStatus::HTTP_NOT_FOUND);
    }

    public static function unauthorized(string $message = 'Autentikasi gagal.'): JsonResponse
    {
        return self::error($message, status: HttpStatus::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'Anda tidak memiliki akses ke sumber daya ini.'): JsonResponse
    {
        return self::error($message, status: HttpStatus::HTTP_FORBIDDEN);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
