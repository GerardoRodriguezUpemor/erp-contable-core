<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface TenantContextInterface
{
    /**
     * Retrieves the strictly verified RFC of the currently authenticated tenant.
     */
    public function getCurrentRfc(): string;

    /**
     * Retrieves the system UUID of the currently authenticated tenant.
     * Required for multi-tenancy and integration event payloads.
     */
    public function getCurrentTenantId(): string;

    /**
     * Retrieves the fiscal regime code of the currently authenticated tenant.
     * (e.g., '625' for Régimen Simplificado de Confianza)
     */
    public function getCurrentRegime(): string;
}
