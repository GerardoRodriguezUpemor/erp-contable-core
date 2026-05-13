<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\Rules\ClassificationRuleInterface;

/**
 * OCP-compliant CFDI ownership resolver using a priority-ordered rule chain.
 *
 * This class acts exclusively as an orchestrator. It does NOT contain:
 * - if/else monolithic logic
 * - hardcoded category decisions
 * - infrastructure access
 * - imports from Fiscal or other bounded contexts
 *
 * To add a new classification rule (e.g., Carta Porte variations):
 * 1. Create a new class implementing ClassificationRuleInterface
 * 2. Inject it via the constructor
 * → Zero modification to this class required (OCP satisfied)
 *
 * Rule priority ordering is functionally significant.
 * SELF_INVOICE (100) must always evaluate before INCOME_ISSUED (60).
 * Changes to priority values must be accompanied by updated ordering tests.
 */
class CfdiOwnershipResolver implements CfdiOwnershipResolverInterface
{
    /** @var ClassificationRuleInterface[] Sorted by priority DESC */
    private array $rules;

    public function __construct(ClassificationRuleInterface ...$rules)
    {
        $this->rules = [...$rules];
        // Sort descending by priority — higher priority rules evaluate first
        usort($this->rules, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    public function resolve(
        string          $tenantRfc,
        string          $emisorRfc,
        string          $receptorRfc,
        SatDocumentType $documentType,
    ): CfdiOwnershipCategory {
        $context = new ClassificationContext($tenantRfc, $emisorRfc, $receptorRfc, $documentType);

        foreach ($this->rules as $rule) {
            if ($rule->isSatisfiedBy($context)) {
                return $rule->getCategory();
            }
        }

        // Defensive fallback — should never reach here when ThirdPartyFallbackRule is registered.
        return CfdiOwnershipCategory::THIRD_PARTY;
    }
}
