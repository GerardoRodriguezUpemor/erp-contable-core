# Reporting & CQRS

In ERPContable, we strictly segregate our Read Operations from our Write Operations (CQRS). Heavy monthly and annual reports must never instantiate DDD Aggregates.

## CQRS Architecture

While the **Domain Layer** (Writes) focuses on invariants, data protection, and behavior, the **Read Models** (Queries) care exclusively about fast, shape-specific data retrieval.

```mermaid
flowchart LR
    Client -->|GET /api/reports/monthly| Controller
    Client -->|POST /api/invoices| Controller
    
    subgraph Write Side (Strict, Slow)
        Controller --> ImportInvoiceUseCase
        ImportInvoiceUseCase --> Invoice[Invoice Aggregate]
        Invoice -->|Validate| PostgresRepo[PostgresInvoiceRepository]
        PostgresRepo --> WriteDb[(PostgreSQL)]
    end
    
    subgraph Read Side (Dumb, Fast)
        Controller --> PostgresMonthlyTaxesQuery
        PostgresMonthlyTaxesQuery -->|Raw Highly Optimized SQL| WriteDb
        WriteDb --> PostgresMonthlyTaxesQuery
        PostgresMonthlyTaxesQuery --> DTO[MonthlyFiscalSummary DTO]
    end
    
    DTO --> Client
```

## Monthly Reporting Logic

To determine the fiscal obligations for a given month (e.g., March 2026), the Read Model executes logic that accounts for cash flow realization.

### The Fiscal View
1. **PUE Invoices:** Any PUE `Invoice` issued in March 2026 is fully realized in March. We sum its `subtotal`, `total`, and specific `Tax` configurations.
2. **PPD Invoices (via Payment Complements):** The original date of a PPD `Invoice` is structurally irrelevant to the tax declaration. If it was issued in January, but a `PaymentComplement` was issued with a payment date inside March 2026, the *proportion* of the taxes associated with that payment is realized in March.

### Interfaces and Data Transfer Objects (DTOs)
Reports use strictly defined Interfaces and primitives, bypassing entities:
* `CalculateMonthlyTaxesQueryInterface.php` defines the contract for fetching.
* `MonthlyFiscalSummary.php` contains raw, scalar properties (e.g., `totalIva`, `totalIsrRetained`, `totalSubtotal`).

By keeping this decoupled, we avoid pulling thousands of Entity graphs into memory and triggering N+1 domain queries, achieving miliseconds response times directly at the database engine level.