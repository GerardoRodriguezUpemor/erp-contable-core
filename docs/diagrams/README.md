# ERPContable - Diagrams & Architecture Source

Este directorio funciona como el repositorio "Source of Truth" de los diagramas del sistema, generados usando **Mermaid.js**. Aísla el código fuente de la documentación visual del texto markdown para un mantenimiento sencillo y renderizado externo, si fuera necesario (ej. para Draw.io o Confluence).

### Directorio de Modelos

| Archivo | Diagrama | Descripción |
| :--- | :--- | :--- |
| `00_architecture_layers.mmd` | **Flowchart** | Detalla el flujo y la segregación estricta de las 4 capas de Domain-Driven Design (Framework, Application, Domain, Infrastructure). |
| `01_domain_entities_erd.mmd` | **ERD** | El modelo entidad-relación lógico para el núcleo fiscal (Invoices, Payment Complements, Taxes y sus relaciones M:N). |
| `02_data_flow_ingestion.mmd` | **Sequence Diagram** | El pipeline de procesamiento idempotente desde el parseo de XML hasta la persistencia y auditoría de eventos. |
| `03_tax_strategy_factory.mmd` | **Class Diagram** | Estructura orientada a objetos (Patrón Strategy) que separa retenciones de traslados según régimen o categoría. |
| `04_cancellation_fsm.mmd` | **State Diagram** | Diagrama de estados finitos inmutables que gobiernan una factura y su comunicación asíncrona con el PAC/SAT. |

*Para modificar un diagrama de los manuales `00_` al `05_`, se debe actualizar la fuente original aquí, e incrustarla en el `md` correspondiente.*