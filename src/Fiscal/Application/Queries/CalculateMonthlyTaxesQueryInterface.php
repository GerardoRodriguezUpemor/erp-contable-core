<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Queries;

interface CalculateMonthlyTaxesQueryInterface
{
    /**
     * Calculates the fiscal totals. The RFC is determined securely via TenantContextInterface.
     */
    public function execute(int $year, int $month): MonthlyFiscalSummary;
}
