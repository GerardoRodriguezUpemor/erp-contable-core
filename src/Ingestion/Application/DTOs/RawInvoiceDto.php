<?php

declare(strict_types=1);

namespace App\Ingestion\Application\DTOs;

use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

readonly class RawInvoiceDto
{
    public function __construct(
        public Uuid $uuid,
        public DateTimeImmutable $emittedAt,
        public string $tipoDeComprobante, // 'I', 'E', 'P'
        public string $metodoPago,        // 'PUE', 'PPD'
        public Money $subtotal,
        public Money $total,
        public string $emisorRfc,
        public string $receptorRfc
    ) {}
}
