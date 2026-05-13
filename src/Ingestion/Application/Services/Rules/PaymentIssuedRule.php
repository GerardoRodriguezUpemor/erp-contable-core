<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 80 — Complemento de Pago emitido por el tenant. */
class PaymentIssuedRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->documentType === SatDocumentType::PAYMENT
            && $context->emisorRfc === $context->tenantRfc;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::PAYMENT_COMPLEMENT_ISSUED;
    }

    public function getPriority(): int { return 80; }
}
