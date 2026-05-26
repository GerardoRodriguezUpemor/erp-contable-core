<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use PHPUnit\Framework\TestCase;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;

/**
 * Behavioral characterization test for a real Regime 625 CFDI.
 *
 * This test was previously flagged as "Risky" because it printed to STDOUT.
 * It has been refactored to use proper assertions, keeping the same fiscal
 * validation: a $1,000.00 base with Regime 625 taxes must yield $1,059.00 total.
 *
 * Formula: $1,000 + $160 (IVA 16%) - $80 (IVA_RET 8%) - $21 (ISR_RET 2.1%) = $1,059.00
 */
class RealOfficialDataTest extends TestCase
{
    public function test_validate_an_official_document_against_regime_625(): void
    {
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');

        // $1,000.00 subtotal → $1,059.00 total after Regime 625 taxes
        $subtotal  = new Money(100000); // $1,000.00
        $totalTest = new Money(105900); // $1,059.00

        $invoice = Invoice::createFromIngestion($uuid, 'I', 'PUE', $subtotal, $totalTest);

        $strategy = new Regime625TaxStrategy();
        foreach ($strategy->calculateTaxes($subtotal) as $tax) {
            $invoice->addTax($tax);
        }

        // 1. No discrepancy with SAT 2-cent tolerance
        $this->assertFalse(
            $invoice->hasFiscalDiscrepancy(),
            'El cálculo de impuestos o el total enviado no cuadran matemáticamente'
        );

        // 2. Pin exact arithmetic: 100000 + 16000 - 8000 - 2100 = 105900
        $this->assertEquals(
            105900,
            $invoice->calculateExpectedTotal()->getCents(),
            'Total debe ser exactamente $1,059.00 (105900 centavos)'
        );
    }
}
