# Step 2: The Ingestion Pipeline & Dependency Inversion

## Purpose
Handling XML from the SAT is messy due to complex namespaces and optional nodes. The Ingestion layer is responsible for parsing this data safely and delivering it to the core application in a clean, framework-agnostic format.

## Implementation Details
- **`RawInvoiceDto`**: A rigid boundary object. It carries data from the raw XML into the application. It has zero logic, just strongly typed properties.
- **`XmlParserInterface`**: The core of the Dependency Inversion Principle. The application depends only on this interface, never on the specific XML library used.
- **`SatXmlParser`**: The concrete implementation. 
  - Uses `registerXPathNamespace` to safely handle `cfdi:` and `tfd:` prefixes.
  - Features **Defensive Programming**: It explicitly checks if nodes like `Emisor` exist before accessing them, throwing a `RuntimeException` to prevent silent "Undefined offset" errors.

## Impact on Architecture
If we ever need to switch from `SimpleXML` to a faster library like `XMLReader` for high-volume processing, the core Application logic remains completely untouched.
