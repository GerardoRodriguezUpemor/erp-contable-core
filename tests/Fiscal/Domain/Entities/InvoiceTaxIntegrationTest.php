<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use PHPUnit\Framework\TestCase;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;

class InvoiceTaxIntegrationTest extends TestCase
{
    public function test_it_forgives_minor_sat_rounding_discrepancies(): void
    {
        // XML says total is 116.01, but pure math says 116.00
        $invoice = Invoice::createFromIngestion(
            new Uuid('123e4567-e89b-12d3-a456-426614174000'),
            'I',
            'PUE',
            Money::fromFloat(100.00),
            Money::fromFloat(116.01) 
        );

        $invoice->addTax(new Tax('IVA', TaxCategory::TRANSFERRED, Money::fromFloat(16.00), 0.16));

        // Difference is 1 cent, which is <= the 2 cent tolerance. Should NOT flag.
        $this->assertFalse($invoice->hasFiscalDiscrepancy());
    }

    public function test_it_catches_true_fiscal_discrepancies(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('123e4567-e89b-12d3-a456-426614174001'),
            'I',
            'PUE',
            Money::fromFloat(100.00),
            Money::fromFloat(116.50) // Difference of 50 cents is mathematically wrong
        );

        $invoice->addTax(new Tax('IVA', TaxCategory::TRANSFERRED, Money::fromFloat(16.00), 0.16));

        // Difference > 2 cents. Should flag.
        $this->assertTrue($invoice->hasFiscalDiscrepancy());
    }
}
