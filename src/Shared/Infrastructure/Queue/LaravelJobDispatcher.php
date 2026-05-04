<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Queue;

use App\Shared\Application\JobDispatcherInterface;
use App\Fiscal\Infrastructure\Jobs\ProcessInvoiceJob;

class LaravelJobDispatcher implements JobDispatcherInterface
{
    public function dispatchProcessInvoice(string $xmlContent, string $taxpayerRegime): void
    {
        // This pushes the job to Redis/Database queue
        ProcessInvoiceJob::dispatch($xmlContent, $taxpayerRegime);
    }
}
