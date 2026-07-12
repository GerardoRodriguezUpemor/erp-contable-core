<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Contracts;

use App\Ingestion\Application\DTOs\RawCfdiDto;
use App\Shared\Domain\ValueObjects\Uuid;

/**
 * Staging repository for raw CFDI documents post-parsing.
 *
 * This is NOT a domain repository. Its purpose is to:
 * - Decouple parsing from business processing
 * - Enable lazy hydration from event-driven listeners
 * - Support minimal integration event payloads (IDs only)
 * - Maintain raw CFDI auditability before classification
 *
 * It must contain ZERO fiscal logic and ZERO ownership logic.
 * It must NOT be confused with domain repositories like InvoiceRepositoryInterface.
 */
interface RawCfdiStagingRepositoryInterface
{
    /**
     * Persists a raw CFDI to the staging store and returns a system-generated
     * cfdiDocumentId (UUID). This ID is used as the lightweight event payload
     * identifier — it is NOT the SAT UUID.
     */
    public function persist(RawCfdiDto $dto, string $tenantId): Uuid;

    /**
     * Retrieves a RawCfdiDto snapshot by system-generated cfdiDocumentId.
     *
     * The returned DTO represents a historical snapshot at ingestion time.
     * Consumers MUST NOT mutate the returned instance.
     */
    public function findById(Uuid $cfdiDocumentId): ?RawCfdiDto;

    /**
     * Checks idempotency: returns true if this (satUuid, tenantId) pair
     * has already been ingested, preventing duplicate staging records.
     */
    public function existsBySatUuid(Uuid $satUuid, string $tenantId): bool;

    /**
     * Flags a staging record for manual review (e.g., THIRD_PARTY documents).
     * Does NOT delete or reject the record — it must be preserved for audit.
     */
    public function flagForReview(Uuid $cfdiDocumentId): void;
}
