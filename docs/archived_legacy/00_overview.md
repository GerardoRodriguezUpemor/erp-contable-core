# ERPContable - Architectural Overview

## Objective
The objective of this module was to build a robust, mathematically sound, and highly decoupled system for processing SAT Invoices (CFDIs) and managing Mexican Fiscal Rules (such as Regime 625 cash flow and tax behavior).

## Architectural Approach
We implemented a **Data Pipeline** coupled with **Domain-Driven Design (DDD)** and the **Dependency Inversion Principle (SOLID)**.

### The Pipeline Structure
1. **Shared Context**: Houses the fundamental, unbreakable Value Objects used across the entire application (e.g., `Money`, `Uuid`).
2. **Ingestion Context**: Responsible for safely extracting data from external XML structures, completely isolated from business logic via rigid DTO boundaries.
3. **Fiscal Core**: The heart of the system. It enforces state machines, cash flow logic (PUE vs PPD), tax behavior (Transferred vs Retained), and SAT rounding tolerances.

## Core Strategies
- **Defensive Programming**: XML parsing actively checks for missing nodes to prevent catastrophic runtime failures.
- **Pure Integer Math**: All monetary values are handled in cents to completely eliminate floating-point leaks.
- **Tolerance Windows**: The system enforces mathematical purity while explicitly allowing up to a 2-cent discrepancy (`MAX_DISCREPANCY_TOLERANCE_CENTS`) to account for the SAT's real-world rounding anomalies.
- **Tell, Don't Ask**: Objects like `Tax` and `PaymentComplement` are smart; they apply their own mathematical rules instead of having their data stripped out by external services.
