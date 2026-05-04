<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface TenantContextInterface
{
    /**
     * Retrieves the strictly verified RFC of the currently authenticated user/system.
     */
    public function getCurrentRfc(): string;
}
