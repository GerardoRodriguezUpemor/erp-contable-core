<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 89 — Nómina recibida por el tenant. */
class PayrollReceivedRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->documentType === SatDocumentType::PAYROLL
            && $context->receptorRfc === $context->tenantRfc;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::PAYROLL_RECEIVED;
    }

    public function getPriority(): int { return 89; }
}
