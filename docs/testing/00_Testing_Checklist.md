# ERPContable - Testing & Verification Checklist

Este documento sirve como la fuente de la verdad para el progreso de la cobertura de pruebas de la aplicación (Test Checker). Marca con una `x` (`- [x]`) conforme los tests vayan siendo implementados y pasen exitosamente en verde.

---

## Fase 1: Pruebas de Dominio (Unitarias - 100% Aisladas)
*Tests que validan las reglas de negocio puras, sin tocar base de datos ni frameworks. Se ubican en `tests/Fiscal/Domain/`.*

### 1.1 Value Objects (Objetos de Valor)
- [x] **`TaxTest.php`**: Validar que un impuesto no puede inicializarse con base, tasa o importe negativo.
- [x] **`TaxTest.php`**: Validar el redondeo SAT (respetar tope de 2 o 6 decimales según regla).
- [x] **`MoneyTest.php`**: Validar inmutabilidad (sumas y restas devuelven una nueva instancia, no modifican la actual).

### 1.2 Estrategias de Impuestos (Tax Engine)
- [x] **`TaxStrategiesTest.php`**: `IvaTrasladado16` - Dado base `$1,000.00` devuelve `$160.00`.
- [x] **`TaxStrategiesTest.php`**: `IsrRetenidoDigitalPlatforms` - Dado base `$1,000.00` devuelve la retención aplicable (ej. `$21.00` para 2.1%).
- [x] **`TaxStrategiesTest.php`**: `IvaRetenido8` - Valida cálculos para la frontera o regímenes específicos.
- [x] **`TaxStrategiesTest.php`**: Validar que los impuestos retenidos nunca sean mayores a los impuestos trasladados.

### 1.3 Agregados, Entidades e Invariantes
- [x] **`InvoiceTest.php`**: Integridad matemática - Lanzar `DomainException` si `Subtotal + Impuestos != Total`.
- [x] **`InvoiceTest.php`**: Tolerancia SAT (ADR 0001) - Aceptar discrepancia de diferencias de hasta `$0.02`.
- [x] **`InvoiceTest.php`**: Inmutabilidad - Lanzar excepción si se intenta modificar un `Invoice` que ya está en estado `CANCELLED`.
- [x] **`InvoiceTest.php`**: Flujo PUE completo (se marca como cobrado y afecta impuestos inmediatamente).
- [x] **`InvoiceTest.php`**: Flujo PPD completo (no afecta impuestos hasta recibir REP).
- [x] **`PaymentComplementTest.php`**: Validar que no se pueda exceder el `amount_received` al distribuir pagos.
- [x] **`PaymentApplicationTest.php`**: Validar relaciones M:N entre monto aplicado y facturas.

---

## Fase 2: Pruebas de Capa de Aplicación (Casos de Uso)
*Tests que comprueban la orquestación del flujo. Se mockean los repositorios y despachadores.*

### 2.1 Ingestión
- [x] **`ImportInvoiceUseCaseTest.php`**: Idempotencia - Abortar o devolver Ã©xito sin impacto si el `uuid_sat` ya existe (validado vÃa Mock del Repositorio).
- [x] **`ImportInvoiceUseCaseTest.php`**: Flujo Exitoso - Llama a `InvoiceRepository->save()` y despacha `InvoiceImportedEvent`.

### 2.2 CancelaciÃ³n
- [x] **`CancelInvoiceUseCaseTest.php`**: MÃ¡quina de Estados - Transiciona un Invoice de `ACTIVE` a `PENDING_CANCELLATION`.
- [x] **`CancelInvoiceUseCaseTest.php`**: Efectos Secundarios - Despacha correctamente el `InvoiceCancelledEvent` con la data correcta.

---

## Fase 3: Pruebas de Infraestructura (Integración)
*Tests que tocan la Base de Datos (PostgreSQL o SQLite) para validar mappers, queries y persistencia.*

### 3.1 Mapping y Persistencia
- [ ] **`PostgresInvoiceRepositoryTest.php`**: `save()` - Inserta un Agregado `Invoice` e impacta la BD respetando tablas `CamelCase` (ADR 0005).
- [ ] **`PostgresInvoiceRepositoryTest.php`**: `findByUuidSat()` - Rehidrata desde Sql a un Agregado `Invoice` puro (con sus Value Objects y Taxes intactos).

### 3.2 Reporting & CQRS
- [ ] **`PostgresMonthlyTaxesQueryTest.php`**: Bypass de Dominio - Ejecuta un query SQL directo filtrando por mes exacto y omitiendo periodos anteriores/posteriores.
- [ ] **`PostgresMonthlyTaxesQueryTest.php`**: Rendimiento - Verifica que devuelve un DTO `MonthlyFiscalSummary` poblado sin instanciar modelos Eloquent completos.
- [ ] **`PostgresMonthlyTaxesQueryTest.php`**: Diferenciación PUE vs PPD - Exclusión de facturas PPD del sumario mensual de impuestos hasta verificar REP aplicado.
- [x] **`PostgresMonthlyTaxesQueryTest.php`**: Exclusión de facturas canceladas del cálculo fiscal (tanto PUE cancelado en el mes, como PUE de mes anterior).
- [x] **`PostgresMonthlyTaxesQueryTest.php`**: Ajuste correcto cuando una factura es cancelada después de haber sido incluida en un periodo previo (extracción o reclasificación en la balanza).

### 3.3 Eventos y Auditoría
- [ ] **`AuditLogSubscriberTest.php`**: Escucha del Evento - Al disparar `InvoiceCancelledEvent`, se inserta un registro en `FiscalAuditLogs`.
- [ ] **`AuditLogSubscriberTest.php`**: Polimorfismo JSON - Valida que el payload del log se guarde con la estructura JSON polimórfica acordada (ADR 0004).

---

## Fase 4: Pruebas Funcionales / E2E (Presentación)
*Pruebas de extremo a extremo simulando peticiones HTTP.*

### 4.1 Ingesta HTTP (Upload)
- [ ] **`InvoiceControllerTest.php`**: Importación Exitosa - `POST /api/invoices/import` con XML válido en Base64 devuelve `201 Created` o `200 OK`.
- [ ] **`InvoiceControllerTest.php`**: DB Check - Valida que la API efectivamente creó el registro en BD de la nueva factura.

### 4.2 Restricciones y Errores
- [ ] **`InvoiceControllerTest.php`**: Validaciones HTTP - Enviar un XML corrupto o con error matemático.
- [ ] **`InvoiceControllerTest.php`**: Manejo de Excepciones - Valida que la API captura la `DomainException` y devuelve un `422 Unprocessable Entity`, no un `500 Server Error`.