# ERPContable - Fiscal Engine Core

## System Overview
ERPContable is a high-performance, strictly bounded fiscal engine designed to handle Mexican accounting rules (specifically Regime 625 - Digital Platforms). It is built using **Domain-Driven Design (DDD)** and **Clean Architecture** to ensure mathematical precision, absolute data integrity, and resilience against frequent tax law changes.

This system does not merely store invoices; it mathematically proves them, manages complex M:N cash flows (PUE vs. PPD), and guarantees transactional integrity across distributed fiscal events.

## Core Capabilities
* **CFDI Ingestion & Validation:** Idempotent processing of raw SAT XMLs.
* **Strict Cash Flow Management:** Automated M:N reconciliation via Payment Complements (REPs) and Payment Applications.
* **Stateless Tax Engine:** Strategy-pattern-based tax calculations (e.g., 16% IVA, 8% Retained IVA, 2.1% Retained ISR).
* **CQRS Reporting:** Highly optimized read models for instant monthly and annual fiscal declarations.
* **Immutable Auditing:** Domain-event-driven, JSON-polymorphic audit ledgers.

## Documentation Index
To onboard successfully, read the documentation in the following order:

1. [Architecture & Design Principles](docs/00_Architecture_Design.md) - *Start here to understand the layers.*
2. [Domain Entities & Aggregates](docs/01_Domain_Core.md) - *The core fiscal rules and structures.*
3. [Data Flow & Lifecycle](docs/02_Data_Flow_Lifecycle.md) - *How an XML becomes a verified financial record.*
4. [Tax Engine](docs/03_Tax_Engine_Strategies.md) - *Calculations, strategies, and rounding tolerances.*
5. [Reporting & CQRS](docs/04_Reporting_CQRS.md) - *How cash flow is aggregated for declarations.*
6. [Cancellations & Consistency](docs/05_Cancellations_Consistency.md) - *State machines and chronological dependency rules.*
7. [Developer Onboarding](docs/onboarding/00_Setup_Guide.md) - *Local setup and contribution guidelines.*
8. [Testing Checklist](docs/testing/00_Testing_Checklist.md) - *Guide and tracker for test coverage phases.*
9. [Value Objects Tests Walkthrough](docs/testing/01_ValueObjects_Walkthrough.md) - *Guide for running isolated domain tests.*

## Technology Stack
* **Language:** PHP 8.2+
* **Framework:** Laravel (used strictly as an infrastructure delivery mechanism)
* **Database:** PostgreSQL (with explicit `CamelCase` table naming conventions)
* **Architecture:** CQRS, DDD, Event-Driven