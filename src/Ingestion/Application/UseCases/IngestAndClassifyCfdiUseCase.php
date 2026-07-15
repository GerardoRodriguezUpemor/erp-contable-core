<?php

declare(strict_types=1);

namespace App\Ingestion\Application\UseCases;

use App\Ingestion\Application\Contracts\RawCfdiStagingRepositoryInterface;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Events\ClassifiedCfdiIngestedIntegrationEvent;
use App\Ingestion\Application\Services\CfdiOwnershipResolverInterface;
use App\Ingestion\Infrastructure\Parsers\XmlParserInterface;
use App\Ingestion\Domain\Validators\XmlValidatorInterface;
use App\Shared\Application\EventDispatcherInterface;
use App\Shared\Application\TenantContextInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * The sole public entry point for all CFDI ingestion.
 *
 * Executes the complete pipeline:
 *   Validate → Parse → Idempotency Check → Stage → Classify → Broadcast
 *
 * No external bounded context should:
 * - invoke the parser directly
 * - invoke ProcessRawXmlUseCase
 * - construct RawCfdiDto manually
 *
 * Fault tolerance: THIRD_PARTY documents are flagged for review but
 * do NOT halt ingestion — the integration event is still dispatched
 * so the audit trail remains complete.
 *
 * Transactional note: In production, staging persistence and event dispatch
 * should be treated as a logical unit (transactional outbox pattern) to prevent
 * orphaned events or persisted records without propagation.
 */
class IngestAndClassifyCfdiUseCase
{
    public function __construct(
        private XmlValidatorInterface               $validator,
        private XmlParserInterface                  $parser,
        private RawCfdiStagingRepositoryInterface   $stagingRepository,
        private TenantContextInterface              $tenantContext,
        private CfdiOwnershipResolverInterface      $resolver,
        private EventDispatcherInterface            $eventDispatcher,
    ) {}

    public function execute(string $xmlContent): void
    {
        // 1. Structural validation — fail fast on malformed XML
        if (!$this->validator->validate($xmlContent)) {
            throw new RuntimeException("Invalid CFDI XML structure. XSD validation failed.");
        }

        // 2. Parse into strongly-typed RawCfdiDto
        $dto = $this->parser->parse($xmlContent);

        // 3. Resolve tenant context
        $tenantId  = $this->tenantContext->getCurrentTenantId();
        $tenantRfc = $this->tenantContext->getCurrentRfc();

        // 4. Idempotency check — prevent duplicate staging records for same (sat_uuid, tenant_id)
        if ($this->stagingRepository->existsBySatUuid($dto->uuid, $tenantId)) {
            return; // Already ingested — silent skip, no re-dispatch
        }

        // 5. Persist raw CFDI to staging — generates system cfdiDocumentId
        $cfdiDocumentId = $this->stagingRepository->persist($dto, $tenantId);

        // 6. Classify ownership — pure rule chain evaluation, no infrastructure access
        $category = $this->resolver->resolve(
            $tenantRfc,
            $dto->emisorRfc,
            $dto->receptorRfc,
            $dto->documentType,
        );

        // 7. Fault tolerance: THIRD_PARTY documents are preserved and flagged, never discarded
        if ($category === CfdiOwnershipCategory::THIRD_PARTY) {
            $this->stagingRepository->flagForReview($cfdiDocumentId);
        }

        // 8. Construct minimal integration event — identifiers only, zero raw data
        $event = new ClassifiedCfdiIngestedIntegrationEvent(
            cfdiDocumentId:        $cfdiDocumentId,
            tenantId:              $tenantId,
            classificationCategory: $category,
            occurredOn:            new DateTimeImmutable(),
        );

        // 9. Broadcast to all registered listeners across bounded contexts
        $this->eventDispatcher->dispatchAll([$event]);
    }
}
