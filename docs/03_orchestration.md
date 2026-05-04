# Step 3: Application Orchestration

## Purpose
Before entering the domain, we need a "Doorman" that coordinates the infrastructure tools. The Use Case ensures that raw data follows a strict protocol before it is considered trustworthy.

## Implementation Details
- **`ProcessRawXmlUseCase`**: The single entry point for our Ingestion layer. It orchestrates two tools:
  - `XmlValidatorInterface`: Ensures the XML string structurally matches the SAT's XSD definitions.
  - `XmlParserInterface`: Extracts the data into a DTO.
- **"Validate First, Parse Second"**: The Use Case strictly enforces this rule. It immediately throws a `RuntimeException` if validation fails, meaning the parser will never attempt to process a fundamentally malformed XML string.

## Impact on Architecture
This Use Case creates a clean Application Service layer. Controllers, CLI scripts, or Queue Workers simply call this class with an XML string and get a safe `RawInvoiceDto` back, completely unaware of the complex validations and XPath extractions happening under the hood.
