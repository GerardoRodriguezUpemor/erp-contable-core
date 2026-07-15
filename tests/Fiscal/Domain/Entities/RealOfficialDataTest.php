<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use PHPUnit\Framework\TestCase;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;

class RealOfficialDataTest extends TestCase
{
    public function test_validate_an_official_document_against_regime_625(): void
    {
        // 1. Ingresa los datos oficiales de tu documento (sin impuestos, el sistema los calculará)
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');
        $tipoComprobante = 'I'; // Ingreso
        $metodoPago = 'PUE';    // Pago en una sola exhibición

        // IMPORTANTE: Los importes deben ser en CENTAVOS. Ej. $1,000.00 pesos = 100000 centavos
        $subtotal = new Money(100000); // INGRESA AQUÍ EL SUBTOTAL DE TU DOCUMENTO

        // INGRESA AQUÍ EL TOTAL OFICIAL AL QUE DEBES LLEGAR (Subtotal + Traslados - Retenciones)
        $totalTest = new Money(105900); // 1000 + 160 (IVA) - 80 (Ret IVA) - 21 (Ret ISR) = 1,059.00 -> 105900 centavos

        // 2. Simulamos crear la Factura desde el Ingestion (XML Parsing)
        $invoice = Invoice::createFromIngestion(
            $uuid,
            $tipoComprobante,
            $metodoPago,
            $subtotal,
            $totalTest
        );

        // 3. Calculamos impuestos aplicando tu Regla 625
        $strategy = new Regime625TaxStrategy();
        $taxes = $strategy->calculateTaxes($subtotal);

        // 4. Pegamos los impuestos a la factura
        foreach ($taxes as $tax) {
            $invoice->addTax($tax);
        }

        // --- IMPRESIÓN PARA QUE VEAS EL RESULTADO ---
        $this->expectOutputRegex('/RESULTADO DE TU DOCUMENTO OFICIAL.*Documento Perfecto/s');

        echo "\n\n=== RESULTADO DE TU DOCUMENTO OFICIAL ===";
        echo "\nSubtotal Base: $" . number_format($subtotal->getCents() / 100, 2);
        
        foreach ($taxes as $tax) {
            $type = $tax->category === TaxCategory::RETAINED ? '-' : '+';
            echo "\n {$type} {$tax->name} (" . ($tax->rate * 100) . "%): $" . number_format($tax->amount->getCents() / 100, 2);
        }

        echo "\n-----------------------";
        echo "\nTotal Calculado por Sistema: $" . number_format($invoice->calculateExpectedTotal()->getCents() / 100, 2);
        echo "\nTotal Oficial Esperado: $" . number_format($totalTest->getCents() / 100, 2);
        
        $discrepancy = $invoice->hasFiscalDiscrepancy();
        echo "\n¿Tiene discrepancia con el SAT?: " . ($discrepancy ? '⚠️ SÍ - Rechazar' : '✅ NO - Documento Perfecto') . "\n";

        // Comprobamos que el test realmente pase matemáticamente
        $this->assertFalse($discrepancy, 'El cálculo de impuestos o el total enviado no cuadran matemáticamente');
    }
}
