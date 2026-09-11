<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Helpers\ApiResponse;
use App\Support\Helpers\Flash;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown by services when a request is well-formed but breaks a domain rule —
 * a full halaqah, a double-booked khatib, a closed check-in window.
 *
 * Rendering itself keeps controllers free of try/catch boilerplate: web requests
 * get a flash message and a redirect back, API requests get JSON.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::error($this->getMessage(), status: $this->status);
        }

        Flash::error($this->getMessage());

        return back()->withInput();
    }
}
