<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Services;

use App\Ingestion\Application\DTOs\SatDocumentType;

/**
 * Immutable Value Object carrying the classification inputs.
 *
 * Passed to each ClassificationRuleInterface to evaluate ownership.
 * Rules must not mutate this context object.
 */
readonly class ClassificationContext
{
    public function __construct(
        public string          $tenantRfc,
        public string          $emisorRfc,
        public string          $receptorRfc,
        public SatDocumentType $documentType,
    ) {}
}
