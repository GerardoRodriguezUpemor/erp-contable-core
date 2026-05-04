# ADR 0002: The Wipe-and-Replace Strategy for Payment Applications

## Status
Accepted

## Context
A Payment Complement (REP) acts as an Aggregate Root that manages multiple `PaymentApplication` entries (the "Documentos Relacionados" in SAT terms). These applications represent partial payments towards specific invoices. Managing the lifecycle of these intermediate links can become complex when a REP is updated or its applications are modified.

## Decision
We implemented a "Wipe-and-Replace" strategy within the `PostgresPaymentComplementRepository`. Whenever a `PaymentComplement` is saved, we delete all existing related rows in the `PaymentApplications` table and re-insert the current state of the aggregate's in-memory collection.

## Consequences
* **Positive:** Guaranteed consistency between the Domain Aggregate state and the database records.
* **Positive:** Eliminates complex "diffing" logic in the repository to track which applications were added, removed, or modified.
* **Negative:** Slightly higher database overhead due to the deletion and re-insertion operations, which is negligible given the typically low number of applications per REP (usually < 100).
* **Positive:** Simplifies the implementation of REP cancellations; clearing the applications collection in memory automatically results in empty tables upon save.
