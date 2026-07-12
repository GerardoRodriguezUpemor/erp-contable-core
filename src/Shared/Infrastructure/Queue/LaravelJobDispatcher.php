<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Queue;

use App\Shared\Application\JobDispatcherInterface;
use Closure;

class LaravelJobDispatcher implements JobDispatcherInterface
{
    private Closure $dispatch;

    /** @param callable(string): void $dispatch Framework-specific queue callback. */
    public function __construct(callable $dispatch)
    {
        $this->dispatch = Closure::fromCallable($dispatch);
    }

    public function dispatchIngestCfdi(string $xmlContent): void
    {
        ($this->dispatch)($xmlContent);
    }
}
