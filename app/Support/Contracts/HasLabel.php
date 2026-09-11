<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Implemented by every backed enum that is rendered in the UI.
 *
 * Identifiers stay in English while `label()` returns the Indonesian text shown
 * to the user, so translation never leaks into business logic.
 */
interface HasLabel
{
    public function label(): string;

    /**
     * Bootstrap contextual colour used for badges and buttons.
     */
    public function color(): string;

    /**
     * Bootstrap Icons class name, without the leading `bi bi-`.
     */
    public function icon(): string;
}
