<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface JobDispatcherInterface
{
    /**
     * Dispatches an asynchronous job to process a single invoice.
     */
    public function dispatchProcessInvoice(string $xmlContent, string $taxpayerRegime): void;
}
