<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\ValueObjects;

use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\Enums\TaxCategory;
use InvalidArgumentException;

readonly class Tax
{
    public function __construct(
        public string $name, // e.g., 'IVA', 'ISR', 'IEPS'
        public TaxCategory $category,
        public Money $amount,
        public float $rate,
        public ?Money $baseAmount = null
    ) {
        if ($rate < 0) {
            throw new InvalidArgumentException("Tax rate cannot be negative. Direction is handled by the TaxCategory.");
        }
        
        if (trim($name) === '') {
            throw new InvalidArgumentException("Tax name cannot be empty.");
        }

        if ($baseAmount !== null) {
            $exactAmountInCents = (float) $baseAmount->getCents() * $rate;
            $providedAmountInCents = (float) $amount->getCents();

            $difference = abs($exactAmountInCents - $providedAmountInCents);

            if ($difference > 2.01) { // Límite de tolerancia de redondeo de 2 centavos
                throw new InvalidArgumentException("Tax amount exceeds SAT rounding tolerance limits.");
            }
        }
    }

    /**
     * Applies this tax to a given running total based on its category behavior.
     */
    public function applyToTotal(Money $currentTotal): Money
    {
        if ($this->category->isAddedToTotal()) {
            return $currentTotal->add($this->amount);
        }

        if ($this->category->isDeductedFromTotal()) {
            return $currentTotal->subtract($this->amount);
        }

        return $currentTotal;
    }
}
