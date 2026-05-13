<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;

/**
 * Contract for the CFDI ownership classification service.
 *
 * Implementations must be pure orchestrators — they must not:
 * - contain if/else monolithic logic
 * - hardcode categories directly
 * - query infrastructure or databases
 * - import any Fiscal or other bounded context namespaces
 */
interface CfdiOwnershipResolverInterface
{
    public function resolve(
        string          $tenantRfc,
        string          $emisorRfc,
        string          $receptorRfc,
        SatDocumentType $documentType,
    ): CfdiOwnershipCategory;
}
