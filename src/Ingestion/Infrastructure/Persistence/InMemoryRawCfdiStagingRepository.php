<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Persistence;

use App\Ingestion\Application\DTOs\RawCfdiDto;
use App\Shared\Domain\ValueObjects\Uuid;

/**
 * In-memory implementation of RawCfdiStagingRepositoryInterface.
 *
 * Intended for unit testing only. Provides full idempotency guarantees
 * via an in-memory SAT UUID index keyed by tenantId.
 */
class InMemoryRawCfdiStagingRepository implements RawCfdiStagingRepositoryInterface
{
    /** @var array<string, RawCfdiDto> cfdiDocumentId => RawCfdiDto */
    private array $store = [];

    /** @var array<string, string[]> tenantId => [satUuid, ...] */
    private array $satUuidIndex = [];

    /** @var array<string, bool> cfdiDocumentId => flagged */
    private array $reviewFlags = [];

    public function persist(RawCfdiDto $dto, string $tenantId): Uuid
    {
        $cfdiDocumentId = new Uuid($this->generateUuid());

        $this->store[$cfdiDocumentId->getValue()] = $dto;
        $this->satUuidIndex[$tenantId][] = strtoupper($dto->uuid->getValue());

        return $cfdiDocumentId;
    }

    public function findById(Uuid $cfdiDocumentId): ?RawCfdiDto
    {
        return $this->store[$cfdiDocumentId->getValue()] ?? null;
    }

    public function existsBySatUuid(Uuid $satUuid, string $tenantId): bool
    {
        return in_array(
            strtoupper($satUuid->getValue()),
            $this->satUuidIndex[$tenantId] ?? [],
            strict: true
        );
    }

    public function flagForReview(Uuid $cfdiDocumentId): void
    {
        $this->reviewFlags[$cfdiDocumentId->getValue()] = true;
    }

    public function isFlaggedForReview(Uuid $cfdiDocumentId): bool
    {
        return $this->reviewFlags[$cfdiDocumentId->getValue()] ?? false;
    }

    /** RFC 4122 v4 UUID generator — no external dependency required. */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
