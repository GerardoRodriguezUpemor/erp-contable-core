<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 100 — Must evaluate before any other rule. */
class SelfInvoiceRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->emisorRfc === $context->tenantRfc
            && $context->receptorRfc === $context->tenantRfc;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::SELF_INVOICE;
    }

    public function getPriority(): int { return 100; }
}
