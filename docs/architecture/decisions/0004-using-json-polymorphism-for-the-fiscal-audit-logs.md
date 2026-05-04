# ADR 0004: Using JSON Polymorphism for the FiscalAuditLogs

## Status
Accepted

## Context
We need a permanent, immutable audit ledger for fiscal events (cancellations, modifications, state changes). Creating a separate table for every type of domain event would lead to schema bloat and complex reporting.

## Decision
We implemented a single `FiscalAuditLogs` table using a polymorphic JSON `payload` column. This table stores the aggregate UUID, the event name, the occurrence timestamp, and all specific event details within the JSON structure.

## Consequences
* **Positive:** Highly flexible schema that can accommodate any current or future domain event without migration changes.
* **Positive:** Simplified query logic for generating unified audit trails for a specific aggregate (e.g., "Show me everything that happened to Invoice X").
* **Negative:** Slightly reduced performance for complex filtering on specific fields within the JSON payload compared to indexed columns (partially mitigated by PostgreSQL's JSONB capabilities if needed later).
* **Positive:** Enforces the "Append-Only" nature of the ledger, as there is no need for updates to individual columns.
