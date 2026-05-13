# Extensibility Blueprint — Adding New ERP Bounded Contexts

## Architecture Guarantee

New ERP modules can be integrated into the CFDI classification pipeline
**without modifying any existing code** in the `Ingestion` or `Fiscal` namespaces.

This is guaranteed by the event-driven listener pattern built around
`ClassifiedCfdiIngestedIntegrationEvent`.

---

## How to Add a New Module (e.g., Expenses, Payroll, Accounting)

### Step 1 — Create the Listener

```
src/{Context}/Application/Listeners/{Context}CfdiListener.php
```

```php
class ProcessExpenseCfdiListener
{
    private const EXPENSE_CATEGORIES = [
        CfdiOwnershipCategory::INCOME_RECEIVED, // Received invoices = expenses for the tenant
    ];

    public function handle(ClassifiedCfdiIngestedIntegrationEvent $event): void
    {
        // 1. Guard clause
        if (!in_array($event->classificationCategory, self::EXPENSE_CATEGORIES, strict: true)) {
            return;
        }

        // 2. Hydration — fetch from staging using cfdiDocumentId
        $dto = $this->stagingRepository->findById($event->cfdiDocumentId);

        // 3. Handoff to Expenses use case
        $this->recordExpenseUseCase->execute($dto, $this->tenantContext->getCurrentRegime());
    }
}
```

### Step 2 — Register in Event Bus

In your framework's event service provider or configuration:

```php
ClassifiedCfdiIngestedIntegrationEvent::class => [
    ProcessIncomeCfdiListener::class,   // Fiscal — already registered
    ProcessExpenseCfdiListener::class,  // Expenses — new, zero Fiscal/Ingestion changes
    ProcessPayrollCfdiListener::class,  // Payroll  — new, zero Fiscal/Ingestion changes
],
```

### Step 3 — Done

**Zero modifications required to:**
- `SatXmlParser`
- `CfdiOwnershipResolver` (unless a new SAT document type appears)
- `IngestAndClassifyCfdiUseCase`
- `ImportInvoiceUseCase`
- Any existing listener

---

## Listener Design Rules

| Rule | Rationale |
|---|---|
| Guard clause first | Drop irrelevant events immediately |
| Hydrate from staging | Never embed data in events (payload stays minimal) |
| Delegate to Use Case | Listeners are wiring, not logic |
| No shared mutable state | Listeners must be async-safe |
| No execution order assumptions | Listeners may run in parallel via queues |

---

## Future Bounded Contexts Roadmap

| Module | Categories to Listen For |
|---|---|
| **Expenses BC** | `INCOME_RECEIVED` |
| **Payroll BC** | `PAYROLL_ISSUED`, `PAYROLL_RECEIVED` |
| **Accounting BC** | All categories (ledger entries) |
| **DIOT** | `INCOME_RECEIVED` from foreign suppliers |
| **Electronic Accounting** | All income/expense categories |
| **SAT Reconciliation** | All categories (status verification) |
| **AI Anomaly Detection** | `THIRD_PARTY`, `SELF_INVOICE` |

---

## Architectural Restriction

> New modules MUST be addable via new listeners, new categories, or new rules
> **without modifying** parsers, existing listeners, aggregates, or public contracts.
>
> This is a central architectural constraint of the ERP system.
> Any change that violates this constraint requires an architecture review.
