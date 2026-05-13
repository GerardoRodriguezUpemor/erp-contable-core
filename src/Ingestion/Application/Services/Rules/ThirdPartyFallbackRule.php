<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/**
 * Priority 0 — Always matches. Acts as the guaranteed fallback.
 *
 * THIRD_PARTY does NOT mean the CFDI is invalid. It may represent:
 * - Operational errors
 * - Resent/duplicated XMLs
 * - Third-party documents
 * - Mis-assigned documents
 * - Future multi-company scenarios
 *
 * Records classified as THIRD_PARTY must be preserved and audited,
 * never discarded automatically.
 */
class ThirdPartyFallbackRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return true; // Always satisfied — guaranteed fallback
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::THIRD_PARTY;
    }

    public function getPriority(): int { return 0; }
}
