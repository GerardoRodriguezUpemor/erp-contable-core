<?php

declare(strict_types=1);

namespace App\Fiscal\Application\UseCases;

use App\Shared\Application\JobDispatcherInterface;
use App\Shared\Application\TenantContextInterface;

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
        // Fetch the RFC securely once for the entire batch
        $rfc = $this->tenantContext->getCurrentRfc();

        foreach ($xmlContents as $xmlContent) {
            // Dispatch to the background queue. 
            // The user does not wait for this to finish.
            $this->dispatcher->dispatchProcessInvoice($xmlContent, $rfc);
        }
    }
}
