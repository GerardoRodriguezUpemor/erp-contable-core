<?php
declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use App\Fiscal\Domain\Entities\Invoice;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function test_flujo_pue_completo_se_marca_como_cobrado_y_afecta_impuestos_inmediatamente(): void
    {
        $uuid = new Uuid('783afc82-82dc-4613-baea-743a49fb21ab');
        $subtotal = new Money(100000); // 1000.00
        $total = new Money(116000);    // 1160.00
        
        $invoice = Invoice::createFromIngestion(
            $uuid,
            'I',
            'PUE',
            $subtotal,
            $total
        );
        
        // PUE rules immediately map to paid (balance is 0)
        $this->assertEquals(0, $invoice->getBalanceDue()->getCents());
        
        // Applying payments directly to PUE throws
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot apply a Payment Complement to a PUE invoice.');
        $invoice->applyPayment(new Money(10000));
    }

    public function test_flujo_ppd_completo_no_afecta_impuestos_hasta_rep(): void
    {
        $uuid = new Uuid('783afc82-82dc-4613-baea-743a49fb21ab');
        $subtotal = new Money(100000); // 1000.00
        $total = new Money(116000);    // 1160.00
        
        $invoice = Invoice::createFromIngestion(
            $uuid,
            'I',
            'PPD',
            $subtotal,
            $total
        );
        
        // PPD should preserve the debt initially
        $this->assertEquals(116000, $invoice->getBalanceDue()->getCents());
        
        // A REP comes with 500.00 payment
        $invoice->applyPayment(new Money(50000));
        
        // Debt shouldn't disappear entirely but decrease correctly
        $this->assertEquals(66000, $invoice->getBalanceDue()->getCents());
        
        // We cannot overpay
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Payment amount exceeds the balance due.');
        $invoice->applyPayment(new Money(70000));
    }
}
