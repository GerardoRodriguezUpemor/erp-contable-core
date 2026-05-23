# Step 2: The Ingestion Pipeline & Event-Driven Routing

## Purpose
Handling XML from the SAT is messy due to complex namespaces and optional nodes. The Ingestion layer is responsible for parsing this data safely, maintaining a raw historical snapshot, classifying its semantic ERP ownership, and broadcasting its availability.

## Implementation Details
- **`SatXmlParser`**: The concrete implementation of `XmlParserInterface`. It extracts SAT data and normalizes the `TipoDeComprobante` into a strict `SatDocumentType` enum. It explicitly avoids any business or fiscal logic.
- **`RawCfdiDto`**: A rigid, immutable transport object. It acts as a strict boundary contract carrying raw data without behavioral methods.
- **`RawCfdiStagingRepository`**: Persists the parsed CFDI into `raw_ingested_cfdis` before any business logic occurs. This guarantees **idempotency** (preventing duplicate SAT UUIDs per tenant) and provides a system `cfdiDocumentId`.
- **`CfdiOwnershipResolver`**: An Open/Closed Principle (OCP) compliant rule engine. It evaluates a priority-ordered chain of rules (e.g., `SelfInvoiceRule`, `IncomeIssuedRule`) to determine which Bounded Context should own the document (e.g., `INCOME_ISSUED`, `PAYROLL_RECEIVED`).
- **`ClassifiedCfdiIngestedIntegrationEvent`**: A lightweight cross-context event dispatched after staging and classification. Its payload is minimal: it carries IDs and the category, but *no heavy XML or raw data*.

## Impact on Architecture
This pipeline establishes strict **Bounded Context Isolation**. The Ingestion layer does not know that the Fiscal module exists. If we need to process "Expenses" tomorrow, the Ingestion code remains untouched; we simply add a new listener in the Expenses context that subscribes to the integration event.
