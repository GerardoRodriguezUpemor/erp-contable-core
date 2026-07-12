<?php

declare(strict_types=1);

namespace App\Fiscal\Application\UseCases;

use App\Shared\Application\JobDispatcherInterface;
use App\Shared\Application\TenantContextInterface;

/**
 * Dispatches bulk CFDI XML strings to the asynchronous ingestion queue.
 *
 * Each XML is dispatched independently to the Ingestion pipeline;
 * processing happens asynchronously via IngestAndClassifyCfdiUseCase
 * inside the queue worker.
 *
 * The taxpayer regime is NOT needed at dispatch time — it is resolved
 * inside ProcessIncomeCfdiListener::handle() via TenantContextInterface
 * when the ingested CFDI arrives at the Fiscal bounded context.
 *
 * Note: This use case is semantically similar to BulkIngestCfdiUseCase
 * in the Ingestion context. It is kept separate to avoid introducing
 * a Fiscal → Ingestion dependency (prohibited by Deptrac rules).
 */
class BulkImportUseCase
{
    public function __construct(
        private JobDispatcherInterface $dispatcher,
        private TenantContextInterface $tenantContext
    ) {}

    /**
     * @param string[] $xmlContents Array of raw XML strings
     */
    public function execute(array $xmlContents): void
    {
        // Validate tenant context is available before dispatching the batch
        $this->tenantContext->getCurrentTenantId();

        foreach ($xmlContents as $xmlContent) {
            $this->dispatcher->dispatchIngestCfdi($xmlContent);
        }
    }
}
