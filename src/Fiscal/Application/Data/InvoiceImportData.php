<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Data;

use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

/** Fiscal-owned input model populated by the ingestion anti-corruption layer. */
readonly class InvoiceImportData
{
    public function __construct(
        public Uuid $uuid,
        public DateTimeImmutable $emittedAt,
        public string $tipoDeComprobante,
        public string $metodoPago,
        public Money $subtotal,
        public Money $total,
    ) {}
}
