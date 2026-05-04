<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Entities;

use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;

readonly class PaymentApplication
{
    public function __construct(
        public Uuid $invoiceUuid,
        public int $installmentNumber, // Parcialidad
        public Money $previousBalance,
        public Money $amountPaid,
        public Money $outstandingBalance
    ) {}
}
