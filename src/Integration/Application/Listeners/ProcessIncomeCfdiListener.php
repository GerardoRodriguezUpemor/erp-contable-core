<?php

declare(strict_types=1);

namespace App\Integration\Application\Listeners;

use App\Fiscal\Application\Data\InvoiceImportData;
use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;
use App\Ingestion\Application\Contracts\RawCfdiStagingRepositoryInterface;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Events\ClassifiedCfdiIngestedIntegrationEvent;
use App\Shared\Application\TenantContextInterface;
use RuntimeException;

/**
 * Anti-corruption entry point between the Ingestion and Fiscal bounded contexts.
 *
 * Responsibilities (PERMITTED — must remain thin):
 * - Guard clause: filter by relevant CfdiOwnershipCategory
 * - Hydration: fetch RawCfdiDto snapshot from staging using cfdiDocumentId
 * - Handoff: delegate to ImportInvoiceUseCase
 *
 * Explicitly PROHIBITED:
 * - Complex fiscal logic
 * - Tax calculations
 * - Aggregate transformations
 * - Duplicating ImportInvoiceUseCase logic
 *
 * Async safety: This listener must NOT depend on shared mutable state,
 * MUST NOT assume synchronous execution, and MUST NOT depend on execution
 * order relative to other listeners. It must be safe to execute via
 * queues, message brokers (Kafka, RabbitMQ), or event streaming.
 *
 * Contract: RawCfdiDto retrieved from staging is a historical snapshot
 * at ingestion time. This listener MUST NOT mutate it.
 *
 * Anti-corruption note: RawCfdiDto is used here strictly as an
 * ingestion contract / transport snapshot — NOT as a shared domain aggregate.
 * No Fiscal-domain types leak into the hydration mechanism.
 */
class ProcessIncomeCfdiListener
{
    /** Categories that the Fiscal context is responsible for processing. */
    private const INCOME_CATEGORIES = [
        CfdiOwnershipCategory::INCOME_ISSUED,
        CfdiOwnershipCategory::INCOME_RECEIVED,
    ];

    public function __construct(
        private RawCfdiStagingRepositoryInterface $stagingRepository,
        private ImportInvoiceUseCase              $importInvoiceUseCase,
        private TenantContextInterface            $tenantContext,
    ) {}

    public function handle(ClassifiedCfdiIngestedIntegrationEvent $event): void
    {
        // Guard clause — drop silently for non-income categories
        // This prevents Payroll, Expenses, Transfer, and THIRD_PARTY events
        // from triggering Fiscal aggregate construction.
        if (!in_array($event->classificationCategory, self::INCOME_CATEGORIES, strict: true)) {
            return;
        }

        // Hydration — fetch the historical RawCfdiDto snapshot from staging.
        // The DTO is NOT transmitted in the event (payload stays minimal).
        // Consumers are responsible for their own hydration.
        $dto = $this->stagingRepository->findById($event->cfdiDocumentId);

        if ($dto === null) {
            throw new RuntimeException(
                "Hydration failed: RawCfdiDto not found for cfdiDocumentId: {$event->cfdiDocumentId->getValue()}. " .
                "The staging record may have been deleted prematurely."
            );
        }

        // Handoff — delegate entirely to the Fiscal use case.
        // The regime is resolved from the authenticated tenant context.
        $invoiceData = new InvoiceImportData(
            uuid: $dto->uuid,
            emittedAt: $dto->emittedAt,
            tipoDeComprobante: $dto->tipoDeComprobante,
            metodoPago: $dto->metodoPago,
            subtotal: $dto->subtotal,
            total: $dto->total,
        );

        $this->importInvoiceUseCase->execute($invoiceData, $this->tenantContext->getCurrentRegime());
    }
}
