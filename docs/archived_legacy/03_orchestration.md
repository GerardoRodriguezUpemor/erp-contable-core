# Step 3: Application Orchestration

## Purpose
Before entering the domain, we need a "Doorman" that coordinates the infrastructure tools. The Use Case ensures that raw data follows a strict protocol before it is considered trustworthy and broadcast to the rest of the system.

## Implementation Details
- **`IngestAndClassifyCfdiUseCase`**: The single public entry point for our Ingestion layer. It orchestrates the full pipeline:
  1. `XmlValidatorInterface`: Ensures the XML string structurally matches the SAT's XSD definitions.
  2. `XmlParserInterface`: Extracts the data into a `RawCfdiDto`.
  3. `RawCfdiStagingRepositoryInterface`: Idempotency check and staging persistence.
  4. `CfdiOwnershipResolverInterface`: Classifies the document to determine ERP ownership.
  5. `EventDispatcherInterface`: Broadcasts the `ClassifiedCfdiIngestedIntegrationEvent`.

- **"Validate First, Parse Second"**: The Use Case strictly enforces this rule. It immediately throws a `RuntimeException` if validation fails, meaning the parser will never attempt to process a fundamentally malformed XML string.

- **Fault Tolerance**: Documents classified as `THIRD_PARTY` do not throw exceptions. They are flagged for review and preserved in the staging table, ensuring no data is ever silently dropped.

## Impact on Architecture
This Use Case creates a clean Application Service layer. Controllers, CLI scripts, or Queue Workers simply call this class with an XML string. They do not need to know about the complex validations, XPath extractions, or event dispatches happening under the hood. All downstream processing is handled asynchronously by listeners.
