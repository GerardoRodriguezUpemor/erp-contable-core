<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 59 — Ingreso recibido por el tenant (factura de compra). */
class IncomeReceivedRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->documentType === SatDocumentType::INVOICE
            && $context->receptorRfc === $context->tenantRfc;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::INCOME_RECEIVED;
    }

    public function getPriority(): int { return 59; }
}
