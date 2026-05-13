<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 90 — Nómina emitida por el tenant. */
class PayrollIssuedRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->documentType === SatDocumentType::PAYROLL
            && $context->emisorRfc === $context->tenantRfc;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::PAYROLL_ISSUED;
    }

    public function getPriority(): int { return 90; }
}
