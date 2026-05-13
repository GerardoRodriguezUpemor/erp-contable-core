# Risk Assessment Map (FLADP Phase 6 Output)

> **Advertencia Forense:** Este mapa diagnostica las áreas de alto peligro estructural. Cualquier intento de refactorizar estos módulos sin pruebas de regresión adicionales tiene una alta probabilidad de corromper datos fiscales en producción.

## 1. Hotspots de Acoplamiento Crítico (Do Not Touch)

### A. El "Dependency Loop" de `Shared`
- **Ubicación:** `src/Shared/Infrastructure/Queue/LaravelJobDispatcher.php`
- **Riesgo:** **CRITICAL**. El módulo *Shared* está importando de forma hardcodeada `ProcessInvoiceJob` del módulo *Fiscal*. 
- **Impacto de Refactor:** Renombrar el Job o intentar mover la lógica de la cola destruirá el Job Dispatcher global del ERP. Esta clase debe ser abstraída mediante interfaces antes de ser modificada.

### B. Leak de Transporte de `Ingestion`
- **Ubicación:** `src/Fiscal/Application/UseCases/ImportInvoiceUseCase.php`
- **Riesgo:** **HIGH**. Importa directamente `RawCfdiDto`. 
- **Impacto de Refactor:** Cambiar el nombre de las propiedades en el XML Parser de *Ingestion* (ej. `tipoDeComprobante` -> `type`) romperá la persistencia de facturas en la base de datos fiscal.

---

## 2. Puntos Ciegos de Testing (Coverage Gaps Asumidos)

Dado que la **Fase 1** reportó la ausencia de un driver de *Coverage* nativo, asumimos por defecto que todo el código I/O (Infraestructura) no está probado exhaustivamente contra casos de fallo reales de base de datos.
- **Riesgo Específico:** `PostgresMonthlyTaxesQuery`. Está altamente acoplado a la Facade `DB::` y propiedades estáticas inyectadas en los tests.
- **Impacto de Refactor:** Migrar a un ORM real (ej. Doctrine o Eloquent) sin escribir *Characterization Tests* puros SQL destruirá los reportes mensuales de impuestos, porque la lógica actual depende del `QueryLog` de Laravel para sus aserciones.

---

## 3. Zonas Seguras Identificadas (Green Zones)

- **El Core Fiscal (`src/Fiscal/Domain/`):** Sorprendentemente limpio. Aggregates como `Invoice` y `PaymentComplement` encapsulan todas sus reglas de negocio (Tolerancia SAT de 2 centavos) de manera aislada sin `God Objects` ni *Mocks* excesivos. Se pueden agregar nuevos métodos de dominio aquí con riesgo casi nulo de efectos secundarios externos.
- **Resolver de Propiedad (`CfdiOwnershipResolver`):** Construido sobre patrón *Chain of Responsibility*. Totalmente seguro de extender añadiendo nuevas reglas (ej. `PayrollRule`) sin alterar las reglas base.
