<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Services\TaxStrategies;

use DateTimeImmutable;

interface TaxStrategyFactoryInterface
{
    /**
     * Resolves the correct tax calculation strategy based on the fiscal context.
     */
    public function create(string $regimeCode, DateTimeImmutable $emittedAt): TaxStrategyInterface;
}
