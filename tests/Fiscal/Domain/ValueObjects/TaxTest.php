<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;

class TaxTest extends TestCase
{
    public function test_it_adds_transferred_tax_to_total(): void
    {
        $currentTotal = Money::fromFloat(100.00); // 100 MXN
        $taxAmount = Money::fromFloat(16.00);     // 16 MXN
        
        $ivaTransferred = new Tax('IVA', TaxCategory::TRANSFERRED, $taxAmount, 0.16);
        
        $newTotal = $ivaTransferred->applyToTotal($currentTotal);
        
        // 100 + 16 = 116
        $this->assertEquals(11600, $newTotal->getCents());
    }

    public function test_it_subtracts_retained_tax_from_total(): void
    {
        $currentTotal = Money::fromFloat(100.00); // 100 MXN
        $taxAmount = Money::fromFloat(8.00);      // 8 MXN
        
        $ivaRetained = new Tax('IVA', TaxCategory::RETAINED, $taxAmount, 0.08);
        
        $newTotal = $ivaRetained->applyToTotal($currentTotal);
        
        // 100 - 8 = 92
        $this->assertEquals(9200, $newTotal->getCents());
    }

    public function test_it_rejects_negative_rates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tax rate cannot be negative.");
        
        new Tax('ISR', TaxCategory::RETAINED, Money::fromFloat(2.10), -0.021);
    }

    public function test_it_enforces_sat_rounding_tolerance_with_base_amount(): void
    {
        // En TDD: Ajustaremos la firma de Tax para que reciba el baseAmount y valide la tolerancia.
        // Base: $11.11, Tasa: 0.16 -> Exacto: $1.7776
        $baseAmount = Money::fromFloat(11.11);
        
        // $1.78 está dentro de los $0.02 de tolerancia permitidos por el SAT
        $taxValido = new Tax('IVA', TaxCategory::TRANSFERRED, Money::fromFloat(1.78), 0.16, $baseAmount);
        $this->assertEquals(178, $taxValido->amount->getCents());

        // $1.83 excedería la tolerancia de $0.02
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tax amount exceeds SAT rounding tolerance limits.");
        
        new Tax('IVA', TaxCategory::TRANSFERRED, Money::fromFloat(1.83), 0.16, $baseAmount);
    }
}
