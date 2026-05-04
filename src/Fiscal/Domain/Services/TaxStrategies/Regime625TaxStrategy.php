<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Services\TaxStrategies;

use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;

class Regime625TaxStrategy implements TaxStrategyInterface
{
    // Current rates for Digital Platforms (Transportation/Delivery)
    private const IVA_RATE = 0.16;
    private const IVA_RETENTION_RATE = 0.08;
    private const ISR_RETENTION_RATE = 0.021;

    public function calculateTaxes(Money $subtotal): array
    {
        $baseCents = $subtotal->getCents();

        // 1. Calculate the standard 16% IVA
        $ivaCents = (int) round($baseCents * self::IVA_RATE);
        $iva = new Tax(
            'IVA', 
            TaxCategory::TRANSFERRED, 
            new Money($ivaCents), 
            self::IVA_RATE
        );

        // 2. Calculate the 8% IVA Retention (Platform withholding)
        $ivaRetCents = (int) round($baseCents * self::IVA_RETENTION_RATE);
        $ivaRet = new Tax(
            'IVA_RET', 
            TaxCategory::RETAINED, 
            new Money($ivaRetCents), 
            self::IVA_RETENTION_RATE
        );

        // 3. Calculate the 2.1% ISR Retention (Platform withholding)
        $isrRetCents = (int) round($baseCents * self::ISR_RETENTION_RATE);
        $isrRet = new Tax(
            'ISR_RET', 
            TaxCategory::RETAINED, 
            new Money($isrRetCents), 
            self::ISR_RETENTION_RATE
        );

        return [$iva, $ivaRet, $isrRet];
    }
}
