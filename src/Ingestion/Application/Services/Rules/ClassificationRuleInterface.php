<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services\Rules;

use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\ClassificationContext;

/**
 * Contract for a single, pure classification rule in the rule chain.
 *
 * Rules MUST:
 * - be independent of each other (no inter-rule knowledge)
 * - be stateless and deterministic
 * - return a consistent result for the same ClassificationContext
 *
 * Rules MUST NOT:
 * - know other rules exist
 * - modify any state
 * - access infrastructure or database
 * - import any Fiscal or other bounded context namespaces
 *
 * Priority determines evaluation order (higher = evaluated first).
 * Priority values are part of the functional behavior contract
 * and must be protected by explicit tests.
 */
interface ClassificationRuleInterface
{
    public function isSatisfiedBy(ClassificationContext $context): bool;

    public function getCategory(): CfdiOwnershipCategory;

    /**
     * Higher value = evaluated earlier in the rule chain.
     * Priority ordering is functionally significant and must not be changed
     * without updating the corresponding ordering tests.
     */
    public function getPriority(): int;
}
