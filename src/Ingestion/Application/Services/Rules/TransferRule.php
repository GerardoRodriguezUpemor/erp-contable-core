<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/** Priority 70 — Traslado (type T) — tenant is always the emitter by SAT definition. */
class TransferRule implements ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool
    {
        return $context->documentType === SatDocumentType::TRANSFER;
    }

    public function getCategory(): CfdiOwnershipCategory
    {
        return CfdiOwnershipCategory::TRANSFER;
    }

    public function getPriority(): int { return 70; }
}
