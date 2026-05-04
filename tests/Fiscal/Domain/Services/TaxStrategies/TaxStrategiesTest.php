<?php
declare(strict_types=1);

namespace Tests\Fiscal\Domain\Services\TaxStrategies;

use App\Fiscal\Domain\Enums\TaxCategory;
use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use App\Shared\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class TaxStrategiesTest extends TestCase
{
    private Regime625TaxStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new Regime625TaxStrategy();
    }

    public function test_iva_trasladado_16_dado_base_1000_devuelve_160(): void
    {
        $base = new Money(100000); // 1000.00
        
        $taxes = $this->strategy->calculateTaxes($base);
        
        $iva = null;
        foreach ($taxes as $tax) {
            if ($tax->name === 'IVA' && $tax->category === TaxCategory::TRANSFERRED) {
                $iva = $tax;
                break;
            }
        }
        
        $this->assertNotNull($iva);
        $this->assertEquals(16000, $iva->amount->getCents());
        $this->assertEquals(0.16, $iva->rate);
    }

    public function test_iva_retenido_8_valida_calculos_para_plataformas(): void
    {
        $base = new Money(100000); // 1000.00
        
        $taxes = $this->strategy->calculateTaxes($base);
        
        $ivaRet = null;
        foreach ($taxes as $tax) {
            if ($tax->name === 'IVA_RET' && $tax->category === TaxCategory::RETAINED) {
                $ivaRet = $tax;
                break;
            }
        }
        
        $this->assertNotNull($ivaRet);
        $this->assertEquals(8000, $ivaRet->amount->getCents());
        $this->assertEquals(0.08, $ivaRet->rate);
    }

    public function test_isr_retenido_plataformas_dado_base_1000_devuelve_retencion_aplicable(): void
    {
        $base = new Money(100000); // 1000.00
        
        $taxes = $this->strategy->calculateTaxes($base);
        
        $isrRet = null;
        foreach ($taxes as $tax) {
            if ($tax->name === 'ISR_RET' && $tax->category === TaxCategory::RETAINED) {
                $isrRet = $tax;
                break;
            }
        }
        
        $this->assertNotNull($isrRet);
        $this->assertEquals(2100, $isrRet->amount->getCents());
        $this->assertEquals(0.021, $isrRet->rate);
    }

    public function test_validar_que_los_impuestos_retenidos_nunca_sean_mayores_a_los_impuestos_trasladados(): void
    {
        $base = new Money(500000); // 5000.00
        
        $taxes = $this->strategy->calculateTaxes($base);
        
        $totalTransferred = 0;
        $totalRetained = 0;
        
        foreach ($taxes as $tax) {
            if ($tax->category === TaxCategory::TRANSFERRED) {
                $totalTransferred += $tax->amount->getCents();
            } elseif ($tax->category === TaxCategory::RETAINED) {
                $totalRetained += $tax->amount->getCents();
            }
        }
        
        $this->assertGreaterThan($totalRetained, $totalTransferred, "Los impuestos retenidos no deberían superar a los trasladados.");
    }
}
