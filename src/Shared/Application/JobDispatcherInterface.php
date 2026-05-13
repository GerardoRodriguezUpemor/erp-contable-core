<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface JobDispatcherInterface
{
    /**
     * Dispatches an asynchronous job to ingest and classify a single CFDI.
     *
     * The taxpayerRegime is no longer required at dispatch time:
     * it is resolved internally by CfdiOwnershipResolver during classification.
     */
    public function dispatchIngestCfdi(string $xmlContent): void;
}
