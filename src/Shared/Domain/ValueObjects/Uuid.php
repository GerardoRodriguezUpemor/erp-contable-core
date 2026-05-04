<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Uuid
{
    public function __construct(
        private string $value
    ) {
        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
            throw new InvalidArgumentException("Invalid UUID format: {$value}");
        }
    }

    public function getValue(): string
    {
        return strtoupper($this->value);
    }
}
