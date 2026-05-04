<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use InvalidArgumentException;
use DomainException;

readonly class Money
{
    public function __construct(
        private int $amountInCents
    ) {
        if ($this->amountInCents < 0) {
            throw new InvalidArgumentException('Money amount cannot be strictly negative.');
        }
    }

    public static function fromFloat(float $amount): self
    {
        return new self((int) round($amount * 100));
    }

    public function getCents(): int
    {
        return $this->amountInCents;
    }

    public function add(Money $other): self
    {
        return new self($this->amountInCents + $other->getCents());
    }

    public function subtract(Money $other): self
    {
        $result = $this->amountInCents - $other->getCents();
        
        if ($result < 0) {
            throw new DomainException('Resulting money amount cannot be negative.');
        }

        return new self($result);
    }
}
