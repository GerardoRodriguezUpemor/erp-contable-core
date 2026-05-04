<?php
declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;

class InvoiceIntegrityTest extends TestCase
{
    public function test_integridad_matematica_lanza_excepcion_si_subtotal_mas_impuestos_difiere_del_total(): void
    {
        $uuid = new Uuid('783afc82-82dc-4613-baea-743a49fb21ab');
        $subtotal = new Money(100000); // 1000.00
        $total = new Money(116000);    // 1160.00 declared
        
        $invoice = Invoice::createFromIngestion($uuid, 'I', 'PPD', $subtotal, $total);
        
        // We add a tax of *only* 150.00 instead of 160.00
        $incorrectTax = new Tax('IVA', TaxCategory::TRANSFERRED, new Money(15000), 0.15); // Rate doesn't matter for the addition, only amount
        $invoice->addTax($incorrectTax);
        
        // Total expected = 1000 + 150 = 1150. But stated is 1160. Discrepancy > 2 cents.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Irrecoverable fiscal discrepancy');
        $invoice->ensureMathematicalIntegrity();
    }

    public function test_tolerancia_sat_acepta_discrepancia_de_hasta_2_centavos(): void
    {
        $uuid = new Uuid('783afc82-82dc-4613-baea-743a49fb21ab');
        $subtotal = new Money(100000); // 1000.00
        $total = new Money(116002);    // 1160.02 declared (2 cents tolerance!)
        
        $invoice = Invoice::createFromIngestion($uuid, 'I', 'PPD', $subtotal, $total);
        
        // True mathematical taxes yield +160.00 = 1160.00 expected.
        $tax = new Tax('IVA', TaxCategory::TRANSFERRED, new Money(16000), 0.16);
        $invoice->addTax($tax);
        
        // SHOULD NOT THROW inside the 2 cents.
        $invoice->ensureMathematicalIntegrity();
        $this->assertFalse($invoice->hasFiscalDiscrepancy());
    }

    public function test_inmutabilidad_lanza_excepcion_al_modificar_invoice_cancelado(): void
    {
        $uuid = new Uuid('783afc82-82dc-4613-baea-743a49fb21ab');
        $invoice = Invoice::createFromIngestion($uuid, 'I', 'PPD', new Money(100000), new Money(116000));
        
        $invoice->markAsCancelledBySat();
        
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify an Invoice that is in CANCELLED status.');
        $invoice->applyPayment(new Money(1000));
    }
}
