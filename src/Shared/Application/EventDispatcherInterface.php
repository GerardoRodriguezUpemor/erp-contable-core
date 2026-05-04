<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface EventDispatcherInterface
{
    /**
     * Dispatches an array of domain events to the system's event bus.
     */
    public function dispatchAll(array $events): void;
}
