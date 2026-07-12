<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Queue;

use App\Shared\Application\JobDispatcherInterface;

/**
 * Framework-agnostic queue dispatcher.
 *
 * Delegates to an injected closure for actual queue interaction.
 * This keeps the Shared context completely decoupled from any
 * bounded context (Fiscal, Ingestion, etc.).
 *
 * The closure is wired by the framework's service container at boot time:
 *   - Laravel: fn(string $xml) => IngestCfdiJob::dispatch($xml)
 *   - Testing: fn(string $xml) => $this->dispatched[] = $xml
 *
 * This replaces the previous implementation that hardcoded
 * App\Fiscal\Infrastructure\Jobs\ProcessInvoiceJob, which created
 * a circular dependency (Shared → Fiscal → Shared).
 */
class LaravelJobDispatcher implements JobDispatcherInterface
{
    /**
     * @param \Closure(string): void $queuePush Callable that pushes XML to the queue
     */
    public function __construct(
        private readonly \Closure $queuePush
    ) {}

    public function dispatchIngestCfdi(string $xmlContent): void
    {
        ($this->queuePush)($xmlContent);
    }
}
