<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Services\TaxStrategies;

use DateTimeImmutable;
use InvalidArgumentException;

class TaxStrategyFactory implements TaxStrategyFactoryInterface
{
    public function create(string $regimeCode, DateTimeImmutable $emittedAt): TaxStrategyInterface
    {
        $year = (int) $emittedAt->format('Y');

        if ($regimeCode === '625') {
            return $this->resolveRegime625($year);
        }

        // Fail loudly if we don't support the regime
        throw new InvalidArgumentException("System Error: No tax strategy defined for regime '{$regimeCode}'.");
    }

    private function resolveRegime625(int $year): TaxStrategyInterface
    {
        // Future-proofing the architecture for annual tax law changes
        return match (true) {
            $year >= 2024 && $year <= 2026 => new Regime625TaxStrategy(),
            // $year === 2027 => new Regime625TaxStrategy2027(),
            default => throw new InvalidArgumentException("System Error: No Regime 625 tax strategy defined for fiscal year {$year}."),
        };
    }
}
