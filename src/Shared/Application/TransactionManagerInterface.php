<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Executes a callback within a database transaction.
     *
     * @param Closure $callback
     * @return mixed
     */
    public function transaction(Closure $callback): mixed;
}
