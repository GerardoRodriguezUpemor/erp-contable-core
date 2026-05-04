<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;
use DomainException;

class MoneyTest extends TestCase
{
    public function test_it_can_add_two_money_objects_immutably(): void
    {
        $moneyA = new Money(1050); // $10.50
        $moneyB = new Money(500);  // $5.00

        $result = $moneyA->add($moneyB);

        $this->assertEquals(1550, $result->getCents());
        $this->assertEquals(1050, $moneyA->getCents()); // Immutability check
    }

    public function test_it_throws_exception_on_negative_initialization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money amount cannot be strictly negative.');

        new Money(-100);
    }

    public function test_it_throws_exception_when_subtraction_results_in_negative(): void
    {
        $moneyA = new Money(500);
        $moneyB = new Money(1000);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Resulting money amount cannot be negative.');

        $moneyA->subtract($moneyB);
    }
}
