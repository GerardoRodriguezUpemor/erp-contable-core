# Arquitectura Emergente REAL (FLADP Phase 3 Output)

> **Advertencia Forense:** Este mapa no refleja diseños deseados ni documentos antiguos. Fue extraído empíricamente de la ejecución de `tests/` y las aserciones de paso.

## 1. Mapeo de Boundary Leaks (Violaciones de Contexto)

**Hallazgo:** Fuga Estructural de Ingestion hacia Fiscal.
- **Origen:** `src/Fiscal/Application/UseCases/ImportInvoiceUseCase.php`
- **Acoplamiento:** `use App\Ingestion\Application\DTOs\RawCfdiDto;`
- **Gravedad:** High. `Fiscal` conoce un DTO propiedad de `Ingestion`. Si `Ingestion` decide renombrar el campo `$dto->tipoDeComprobante` a `$dto->documentType`, la capa profunda de `Fiscal` se rompe.
- **Evidencia:** Detectado usando AST/ripgrep cruzado y constructores.

---

## 2. Diagrama de Flujo REAL (Happy Path Comprobado)

Basado en `IngestAndClassifyCfdiUseCaseTest` y `ProcessIncomeCfdiListenerTest`:

```mermaid
sequenceDiagram
    participant External as Controller/CLI
    box Ingestion Context
        participant IngestUC as IngestAndClassifyCfdiUseCase
        participant Validator as XmlValidator
        participant Parser as XmlParser
        participant Staging as RawCfdiStagingRepository
        participant Resolver as CfdiOwnershipResolver
    end
    participant EventBus as EventDispatcher
    box Fiscal Context
        participant Listener as ProcessIncomeCfdiListener
        participant ImportUC as ImportInvoiceUseCase
        participant TaxFactory as TaxStrategyFactory
        participant Invoice as Invoice (Aggregate)
        participant FiscalRepo as InvoiceRepository
    end

    External->>IngestUC: execute(xmlString)
    IngestUC->>Validator: validate(xmlString)
    IngestUC->>Parser: parse() -> RawCfdiDto
    IngestUC->>Staging: existsBySatUuid(uuid) -> false
    IngestUC->>Staging: persist(RawCfdiDto) -> cfdiDocumentId
    IngestUC->>Resolver: resolve(...) -> INCOME_ISSUED
    IngestUC->>EventBus: dispatch(ClassifiedCfdiIngestedIntegrationEvent)
    
    EventBus-->>Listener: handle(event)
    Listener->>Listener: is INCOME category? (Yes)
    Listener->>Staging: findById(cfdiDocumentId) -> RawCfdiDto (LEAK!)
    Listener->>ImportUC: execute(RawCfdiDto, regime)
    
    ImportUC->>FiscalRepo: exists(uuid) -> false
    ImportUC->>Invoice: createFromIngestion(...)
    ImportUC->>TaxFactory: create(regime) -> TaxStrategy
    ImportUC->>TaxStrategy: calculateTaxes(subtotal)
    ImportUC->>Invoice: addTax(Tax)
    ImportUC->>Invoice: hasFiscalDiscrepancy() -> false
    ImportUC->>FiscalRepo: save(Invoice)
    ImportUC->>EventBus: dispatch(InvoiceImportedEvent)
```

---

## 3. Diagnóstico de God Objects

- **Abuso de Servicios Gigantes:** NEGATIVO. Los Use Cases mantienen <6 dependencias y delegan apropiadamente.
- **Aggregates Anémicos:** NEGATIVO. `Invoice` gestiona sus propias invariantes matemáticas, validaciones de vida (PUE vs PPD) y disparos de eventos (`markAsCancelledBySat`).
- **Coordinadores Excesivos:** NEGATIVO. 

**Conclusión Estructural:** El sistema monolítico tiene Boundaries bien definidos a nivel de comportamiento (buen diseño funcional), pero sufre de un "Leak de Transporte" (el DTO viaja de un BC a otro sin mapeo anti-corrupción).
