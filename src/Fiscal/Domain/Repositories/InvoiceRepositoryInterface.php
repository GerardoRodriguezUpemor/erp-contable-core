<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Repositories;

use App\Fiscal\Domain\Entities\Invoice;
use App\Shared\Domain\ValueObjects\Uuid;

interface InvoiceRepositoryInterface
{
    public function findById(Uuid $uuid): ?Invoice;
    public function save(Invoice $invoice): void;
    public function exists(Uuid $uuid): bool;
}
