<?php
declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use App\Fiscal\Domain\Entities\PaymentComplement;
use App\Fiscal\Domain\Entities\PaymentApplication;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class PaymentComplementTest extends TestCase
{
    public function test_validar_que_no_se_pueda_exceder_el_amount_received_al_distribuir_pagos(): void
    {
        $complement = new PaymentComplement(
            new Uuid('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            new DateTimeImmutable(),
            new Money(50000) // 500.00
        );
        
        // Add 300.00 to Invoice A
        $app1 = new PaymentApplication(
            new Uuid('11111111-2222-3333-4444-555555555555'),
            1,
            new Money(100000), // pre-balance
            new Money(30000),  // amount paid
            new Money(70000)   // out balance
        );
        $complement->addApplication($app1);
        
        // Add 250.00 to Invoice B (Exceeds by 50.00)
        $app2 = new PaymentApplication(
            new Uuid('66666666-7777-8888-9999-000000000000'),
            1,
            new Money(50000),  // pre
            new Money(25000),  // amount
            new Money(25000)   // out
        );
        
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot apply payment: The amount exceeds the remaining unapplied balance of this REP.');
        $complement->addApplication($app2);
    }
}
