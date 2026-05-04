<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Services\TaxStrategies;

use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\ValueObjects\Tax;

interface TaxStrategyInterface
{
    /**
     * @return Tax[]
     */
    public function calculateTaxes(Money $subtotal): array;
}
