<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Events;

use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Shared\Domain\Events\IntegrationEventInterface;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

/**
 * Integration Event dispatched after a CFDI has been parsed, staged, and classified.
 *
 * PAYLOAD CONTRACT — This event intentionally carries only identifiers:
 * - cfdiDocumentId: system-generated UUID for the staging record
 * - tenantId:       UUID of the authenticated tenant
 * - classificationCategory: the resolved ERP ownership category
 * - occurredOn:     event timestamp
 *
 * It does NOT and MUST NOT carry:
 * - XML strings
 * - RawCfdiDto instances
 * - Aggregates or Entities
 * - Tax calculations
 *
 * Consumers (Listeners) are responsible for hydrating their own full data
 * by querying RawCfdiStagingRepositoryInterface using cfdiDocumentId.
 * This ensures: no memory bloat, no tight coupling, clean event versioning.
 *
 * This event must remain serializable without any framework-specific infrastructure.
 */
readonly class ClassifiedCfdiIngestedIntegrationEvent implements IntegrationEventInterface
{
    public function __construct(
        public Uuid                   $cfdiDocumentId,
        public string                 $tenantId,
        public CfdiOwnershipCategory  $classificationCategory,
        public DateTimeImmutable      $occurredOn,
    ) {}
}
