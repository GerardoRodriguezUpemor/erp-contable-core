# 6. Event-Driven CFDI Classification Engine

Date: 2026-05-12

## Status

Accepted

## Context

The system previously coupled CFDI XML parsing and ingestion directly with Fiscal domain processing (`ImportInvoiceUseCase`). This monolithic approach forced the Ingestion context to understand Fiscal semantics (e.g., what constitutes an "Income" invoice vs. a "Payroll" invoice vs. an "Expense"), breaking bounded context isolation. It also made the system rigid and difficult to extend for new modules like Expenses or Accounting, which would require modifying the core ingestion logic.

Furthermore, ingestion was not perfectly idempotent, and payloads crossing between modules contained heavy XML or full DTOs, increasing memory bloat.

## Decision

We are implementing a fully decoupled, event-driven architecture for CFDI ingestion and routing:

1. **Agnostic Parsing & Normalization**: The parser now returns a strictly typed `RawCfdiDto` that relies on official SAT taxonomy (`SatDocumentType`), containing zero internal ERP semantics.
2. **Staging Persistence**: Raw CFDIs are immediately persisted to a staging repository (`raw_ingested_cfdis`) upon parsing. This guarantees idempotency via a `(sat_uuid, tenant_id)` unique constraint and provides a system-generated `cfdiDocumentId`.
3. **OCP-Compliant Rule Engine**: Ownership classification is handled by a `CfdiOwnershipResolver` utilizing a priority-ordered chain of pure rule classes (e.g., `IncomeIssuedRule`, `PayrollReceivedRule`). New document logic can be added without modifying existing code.
4. **Lightweight Integration Events**: Once classified, an `IntegrationEvent` (`ClassifiedCfdiIngestedIntegrationEvent`) is dispatched. Crucially, the payload contains *only identifiers* (UUIDs) and the classification category.
5. **Anti-Corruption Listeners**: Downstream bounded contexts (like Fiscal) subscribe to the event via listeners (e.g., `ProcessIncomeCfdiListener`). The listener acts as an anti-corruption layer: it filters irrelevant events, hydrates the `RawCfdiDto` snapshot from staging, and delegates to its own context's Use Case.

## Consequences

### Positive
- **Strict Bounded Context Isolation**: Ingestion knows nothing about Fiscal logic; Fiscal knows nothing about XML parsing.
- **High Extensibility**: Adding a new module (e.g., "Expenses") requires zero changes to the Ingestion core. We just add a `ProcessExpenseCfdiListener` that listens for `INCOME_RECEIVED` events.
- **Guaranteed Idempotency**: The staging layer enforces exactly-once ingestion per tenant.
- **Fault Tolerance**: Unclassifiable or third-party documents (`THIRD_PARTY` category) are preserved in staging and flagged for manual review rather than throwing hard errors that drop the data.
- **Performance**: Events are lightweight, making async queueing highly efficient.

### Negative
- **Increased Complexity**: Tracing the flow of a document now requires understanding the event bus and listener subscriptions.
- **Eventual Consistency**: Staging, classification, and final processing are now distributed steps, requiring transaction outbox patterns for full resilience in production.
