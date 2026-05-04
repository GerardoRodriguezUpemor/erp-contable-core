<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Queries;

use App\Fiscal\Application\Queries\CalculateMonthlyTaxesQueryInterface;
use App\Fiscal\Application\Queries\MonthlyFiscalSummary;
use App\Shared\Application\TenantContextInterface;
use App\Shared\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class PostgresMonthlyTaxesQuery implements CalculateMonthlyTaxesQueryInterface
{
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {}

    public function execute(int $year, int $month): MonthlyFiscalSummary
    {
        $rfc = $this->tenantContext->getCurrentRfc();

        // Stream 1: Immediate Cash Flow (PUE Invoices)
        $pueTotals = DB::table('invoices')
            ->join('cfdi_documents', 'invoices.id', '=', 'cfdi_documents.id')
            ->where('cfdi_documents.rfc_emisor', $rfc)
            ->where('invoices.metodo_pago', 'PUE')
            ->where('invoices.sat_status', 'ACTIVE')
            ->whereYear('cfdi_documents.emitted_at', $year)
            ->whereMonth('cfdi_documents.emitted_at', $month)
            ->selectRaw('COALESCE(SUM(invoices.subtotal_cents), 0) as collected_subtotal')
            ->first();

        // Stream 2: Deferred Cash Flow (PPD Applications via REPs)
        // To get the exact subtotal proportion of a partial payment, we calculate the ratio 
        // of amountPaidCents against the original invoice totalCents.
        $ppdTotals = DB::table('PaymentApplications')
            ->join('PaymentComplements', 'PaymentApplications.paymentUuid', '=', 'PaymentComplements.id')
            ->join('invoices', 'PaymentApplications.invoiceUuid', '=', 'invoices.id')
            ->join('cfdi_documents', 'invoices.id', '=', 'cfdi_documents.id')
            ->where('cfdi_documents.rfc_emisor', $rfc)
            ->where('invoices.sat_status', 'ACTIVE')
            ->whereYear('PaymentComplements.paymentDate', $year)
            ->whereMonth('PaymentComplements.paymentDate', $month)
            ->selectRaw('COALESCE(SUM(
                PaymentApplications.amountPaidCents * (invoices.subtotal_cents::float / invoices.total_cents::float)
            ), 0) as collected_subtotal')
            ->first();

        // Combine the streams
        $totalCollectedSubtotalCents = (int) $pueTotals->collected_subtotal + (int) $ppdTotals->collected_subtotal;
        $collectedIncome = new Money($totalCollectedSubtotalCents);

        // For Regime 625, we can strictly derive the taxes from the collected base 
        // since the rates are fixed (16% IVA, 8% Ret, 2.1% ISR).
        // If rates were variable per invoice item, we would SUM() the exact tax tables instead.
        $ivaTransferred = new Money((int) round($totalCollectedSubtotalCents * 0.16));
        $ivaRetained = new Money((int) round($totalCollectedSubtotalCents * 0.08));
        $isrRetained = new Money((int) round($totalCollectedSubtotalCents * 0.021));

        return new MonthlyFiscalSummary(
            $year,
            $month,
            $collectedIncome,
            $ivaTransferred,
            $ivaRetained,
            $isrRetained
        );
    }
}
