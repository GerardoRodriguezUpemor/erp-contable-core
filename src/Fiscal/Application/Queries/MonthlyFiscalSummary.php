<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Queries;

use App\Shared\Domain\ValueObjects\Money;

readonly class MonthlyFiscalSummary
{
    public function __construct(
        public int $year,
        public int $month,
        public Money $collectedIncome,     // Base gravable (Ingresos efectivamente cobrados)
        public Money $ivaTransferred,      // IVA 16% cobrado al cliente
        public Money $ivaRetained,         // IVA 8% retenido por la plataforma
        public Money $isrRetained          // ISR 2.1% retenido por la plataforma
    ) {}

    /**
     * Helper to calculate the final IVA payable to the SAT.
     * Formula: IVA Transferred - IVA Retained - IVA Creditable (from expenses, to be added later)
     */
    public function calculateNetIvaLiability(Money $ivaCreditableFromExpenses): Money
    {
        $liabilityCents = $this->ivaTransferred->getCents() 
                        - $this->ivaRetained->getCents() 
                        - $ivaCreditableFromExpenses->getCents();

        return new Money(max(0, $liabilityCents)); // Cannot be negative
    }
}
