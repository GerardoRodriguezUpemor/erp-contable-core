<?php

declare(strict_types=1);

namespace App\Shared\Domain\Events;

/**
 * Marker interface for Integration Events.
 *
 * Integration Events cross bounded context boundaries.
 * They differ from Domain Events in that:
 * - They are consumed by external bounded contexts (via listeners, queues, or message brokers)
 * - Their payloads must be minimal (IDs only — no aggregates, DTOs, or XML)
 * - They must be serializable without framework-specific infrastructure
 *
 * Bounded context consumers are responsible for hydrating
 * their own full data using the provided identifiers.
 */
interface IntegrationEventInterface
{
}
