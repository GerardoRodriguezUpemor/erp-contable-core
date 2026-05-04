<?php
declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use App\Fiscal\Domain\Entities\PaymentApplication;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;

class PaymentApplicationTest extends TestCase
{
    public function test_validar_relaciones_multin_entre_monto_aplicado_y_facturas(): void
    {
        // Enforce the logic that previous_balance - amount_paid === outstanding_balance
        $invoiceUuid = new Uuid('11111111-2222-3333-4444-555555555555');
        $previous = new Money(50000); // 500.00
        $paid = new Money(15000);     // 150.00
        $outstanding = new Money(35000); // 350.00
        
        $application = new PaymentApplication(
            $invoiceUuid,
            1,
            $previous,
            $paid,
            $outstanding
        );
        
        $this->assertEquals(
            $application->outstandingBalance->getCents(),
            $application->previousBalance->subtract($application->amountPaid)->getCents()
        );
    }
}
