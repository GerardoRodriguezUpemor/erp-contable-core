<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Repositories;

use App\Fiscal\Domain\Entities\PaymentComplement;
use App\Shared\Domain\ValueObjects\Uuid;

interface PaymentComplementRepositoryInterface
{
    public function exists(Uuid $uuid): bool;
    public function findById(Uuid $uuid): ?PaymentComplement;
    public function save(PaymentComplement $paymentComplement): void;
}
