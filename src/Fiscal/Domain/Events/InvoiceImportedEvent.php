<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Events;

use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

readonly class InvoiceImportedEvent
{
    public function __construct(
        public Uuid $invoiceUuid,
        public DateTimeImmutable $importedAt
    ) {}
}

