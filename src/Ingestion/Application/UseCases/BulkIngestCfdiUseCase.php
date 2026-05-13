<?php

declare(strict_types=1);

namespace App\Ingestion\Application\UseCases;

use App\Shared\Application\JobDispatcherInterface;
use App\Shared\Application\TenantContextInterface;

/**
 * Dispatches bulk CFDI XML strings to the asynchronous ingestion queue.
 *
 * Moved from Fiscal context — bulk ingestion is an Ingestion concern, not Fiscal.
 * Each XML is dispatched independently; processing happens asynchronously via
 * IngestAndClassifyCfdiUseCase inside the queue worker.
 */
class BulkIngestCfdiUseCase
{
    public function __construct(
        private JobDispatcherInterface $dispatcher,
        private TenantContextInterface $tenantContext,
    ) {}

    /**
     * @param string[] $xmlContents Array of raw CFDI XML strings
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
