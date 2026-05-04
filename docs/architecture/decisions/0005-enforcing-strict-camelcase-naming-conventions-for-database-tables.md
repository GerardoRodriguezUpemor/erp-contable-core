# ADR 0005: Enforcing Strict CamelCase Naming Conventions for Database Tables

## Status
Accepted

## Context
Standard Laravel projects typically utilize `snake_case` for database table names (e.g., `cfdi_documents`). However, the existing system design and legacy integration requirements for this project specify `CamelCase` (e.g., `PaymentComplements`).

## Decision
We decided to strictly enforce `CamelCase` for all new fiscal and payment-related tables (specifically `PaymentComplements`, `PaymentApplications`, and `FiscalAuditLogs`).

## Consequences
* **Positive:** Maintains alignment with the original system design specifications.
* **Positive:** Distinguishes the newer, strictly-modeled fiscal domain tables from standard Laravel utility tables.
* **Negative:** Requires manual configuration of the `$table` property in Eloquent models, as it breaks Laravel's default pluralization/naming convention.
* **Negative:** Potential for developer confusion if the project carries a mix of naming styles between older/standard tables and the new fiscal tables.
