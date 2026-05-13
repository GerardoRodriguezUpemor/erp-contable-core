<?php

declare(strict_types=1);

namespace App\Ingestion\Application\DTOs;

use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

/**
 * Raw CFDI transport object produced by the Ingestion parsing layer.
 *
 * This DTO is a strict data transport object. It must remain completely
 * agnostic of the Fiscal domain. It must NOT contain:
 * - fiscal calculations
 * - ownership classification
 * - SAT semantic rules
 * - behavioral methods
 *
 * It carries the normalized SatDocumentType alongside the raw
 * tipoDeComprobante string for full traceability.
 *
 * Consumers must treat instances retrieved from staging as historical
 * snapshots. They must NOT mutate this DTO.
 */
readonly class RawCfdiDto
{
    public function __construct(
        public Uuid            $uuid,
        public DateTimeImmutable $emittedAt,
        public SatDocumentType $documentType,       // Normalized SAT taxonomy
        public string          $tipoDeComprobante,  // Raw SAT code — kept for traceability
        public string          $metodoPago,         // 'PUE', 'PPD', or 'N/A' for non-applicable types
        public Money           $subtotal,
        public Money           $total,
        public string          $emisorRfc,
        public string          $receptorRfc,
    ) {}
}
