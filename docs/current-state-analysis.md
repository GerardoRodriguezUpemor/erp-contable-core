# ERP Contable — Estado Actual del Sistema (Mayo 2026)

> **Documento generado por auditoría forense FLADP + análisis exhaustivo del codebase.**  
> **Fecha:** 2026-05-23  
> **Objetivo:** Registrar exactamente qué fue desarrollado hasta este momento, antes de implementar nuevas funcionalidades.

---

## 1. Arquitectura General

El ERP Contable es un **monolito DDD en PHP puro** (sin framework runtime) con 3 Bounded Contexts:

```
src/
├── Ingestion/   → Recepción, validación, parseo y clasificación de XMLs CFDI
├── Fiscal/      → Dominio contable: facturas, impuestos, cancelaciones, reportes
└── Shared/      → Utilería común: Money, Uuid, interfaces de infraestructura
```

**Stack tecnológico:**
- PHP 8.5.1 (ZTS)
- PHPUnit 13.1.8
- PHPStan 2.1.54
- Deptrac 1.0.2
- Sin Laravel runtime (solo interfaces y fachadas para infraestructura futura)

---

## 2. Capacidades Implementadas

### ✅ Ingesta de CFDIs (Contexto: Ingestion)

| Capacidad | Estado | Detalle |
|-----------|--------|---------|
| Validación XML | ✅ Completo | `XmlValidatorInterface` valida estructura XSD |
| Parseo a DTO | ✅ Completo | `SatXmlParser` extrae datos a `RawCfdiDto` |
| Clasificación de propiedad | ✅ Completo | `CfdiOwnershipResolver` con Chain of Responsibility (9 reglas) |
| Staging | ✅ Completo | Persistencia temporal antes del procesamiento fiscal |
| Idempotencia | ✅ Completo | Previene duplicados por `(satUuid, tenantId)` |
| Zero Data Loss | ✅ Completo | `THIRD_PARTY` se preserva y marca para revisión |
| Eventos de integración | ✅ Completo | `ClassifiedCfdiIngestedIntegrationEvent` con payload mínimo (solo IDs) |

**Tipos de documento SAT soportados en clasificación:**

| Código SAT | Tipo | Clasificación | ¿Se procesa fiscalmente? |
|------------|------|---------------|--------------------------|
| **I** | Ingreso | INCOME_ISSUED / INCOME_RECEIVED | ✅ SÍ |
| **E** | Egreso (Nota de Crédito) | ⚠️ Cae a THIRD_PARTY (sin regla propia) | ❌ NO |
| **P** | Pago (REP) | PAYMENT_COMPLEMENT_ISSUED / RECEIVED | ❌ NO |
| **N** | Nómina | PAYROLL_ISSUED / PAYROLL_RECEIVED | ❌ NO |
| **T** | Traslado | TRANSFER | ❌ NO |

**9 Categorías de propiedad (por prioridad):**

| # | Categoría | Prioridad | Lógica |
|---|-----------|-----------|--------|
| 1 | SELF_INVOICE | 100 | emisor == receptor == tenant |
| 2 | PAYROLL_ISSUED | 90 | tipo=N, emisor=tenant |
| 3 | PAYROLL_RECEIVED | 89 | tipo=N, receptor=tenant |
| 4 | PAYMENT_COMPLEMENT_ISSUED | 80 | tipo=P, emisor=tenant |
| 5 | PAYMENT_COMPLEMENT_RECEIVED | 79 | tipo=P, receptor=tenant |
| 6 | TRANSFER | 70 | tipo=T (cualquier RFC) |
| 7 | INCOME_ISSUED | 60 | tipo=I, emisor=tenant |
| 8 | INCOME_RECEIVED | 59 | tipo=I, receptor=tenant |
| 9 | THIRD_PARTY | 0 | fallback (siempre true) |

---

### ✅ Dominio Fiscal (Contexto: Fiscal)

#### Entidades de Dominio

**Invoice (Aggregate Root) — 272 líneas**
- Constructor privado con factory methods (`createFromIngestion`, `reconstitute`)
- Propiedades: uuid, tipoDeComprobante (I/E), metodoPago (PUE/PPD), subtotal, total, balanceDue, satStatus, lifecycleState, taxes[]
- Reglas de negocio embebidas:
  - **PUE:** `balanceDue = 0` al crear (cobro inmediato)
  - **PPD:** `balanceDue = total` al crear (pendiente hasta REP)
  - `applyPayment()` — Decrementa balance, prohíbe sobrepago
  - `reversePayment()` — Restaura balance, prohíbe exceder total
  - `markAsCancelledBySat()` — Cancela y registra evento de dominio
  - `addTax()` — Agrega impuestos (prohíbe en LOCKED o CANCELLED)
  - `hasFiscalDiscrepancy()` — Compara total calculado vs declarado (tolerancia 2 centavos)
  - `ensureMathematicalIntegrity()` — Aborta si hay discrepancia irrecuperable
  - Protección LOCKED contra modificaciones post-declaración anual

**PaymentComplement — Entidad de complemento de pago**
- Propiedades: uuid, paymentDate, totalReceived, satStatus, applications[]
- `addApplication()` — Registra pago parcial, prohíbe exceder totalReceived + 2 centavos
- `verifyTotalIntegrity()` — Invariante diferida que valida suma de aplicaciones
- `cancel()` — Cancela y limpia todas las aplicaciones

**PaymentApplication — Entidad de aplicación de pago**
- Vincula un pago a una factura específica (installmentNumber, previousBalance, amountPaid, outstandingBalance)

#### Value Objects

**Money** — Inmutable, basado en centavos, prohíbe valores negativos, aritmética segura (add/subtract)

**Tax** — Nombre, categoría (TRANSFERRED/RETAINED), monto, tasa, baseAmount opcional. Valida tolerancia SAT en el constructor.

**Uuid** — Wrapper tipado para UUIDs SAT

#### Enums

| Enum | Valores | Uso |
|------|---------|-----|
| `SatStatus` | ACTIVE, CANCELLED, PENDING_CANCELLATION | Estado ante el SAT |
| `LifecycleState` | IMPORTED, PROCESSED, DECLARED, LOCKED | Ciclo de vida interno |
| `TaxCategory` | TRANSFERRED, RETAINED | Distingue IVA trasladado de IVA retenido |

#### Motor de Impuestos

**Patrón:** Strategy + Factory

**Interfaz:** `TaxStrategyInterface::calculateTaxes(Money $subtotal): array<Tax>`

**Implementaciones:**

| Régimen | Clase | Tasas |
|---------|-------|-------|
| 625 (RESICO Plataformas) | `Regime625TaxStrategy` | IVA 16% trasladado, IVA 8% retenido, ISR 2.1% retenido |

**Factory:** `TaxStrategyFactory` — Resuelve por código de régimen + año fiscal. Soporta años 2024–2026 para el 625. Lanza excepción para regímenes o años no soportados.

**⚠️ Extensibilidad confirmada:** Para agregar un nuevo régimen, solo se necesita:
1. Crear una nueva clase que implemente `TaxStrategyInterface`
2. Registrarla en el `TaxStrategyFactory`

---

### ✅ Casos de Uso Implementados

#### `ImportInvoiceUseCase`
1. Idempotencia por UUID
2. Construye aggregate `Invoice::createFromIngestion()`
3. Ejecuta `TaxStrategy::calculateTaxes()` según régimen del tenant
4. Agrega cada Tax al Invoice
5. Valida integridad fiscal (`hasFiscalDiscrepancy()`)
6. Persiste atómicamente + despacha `InvoiceImportedEvent`

#### `CancelInvoiceUseCase`
1. Busca factura por UUID
2. **Protección PPD:** Si tiene pagos aplicados (balance < total), prohíbe cancelar sin cancelar REPs primero
3. Delega a `Invoice::markAsCancelledBySat()`
4. Persiste + despacha evento

#### `BulkImportUseCase` (Fiscal)
- Wrapper para importar múltiples facturas en batch

#### `IngestAndClassifyCfdiUseCase` (Ingestion)
- Pipeline completo: Validar → Parsear → Idempotencia → Staging → Clasificar → Broadcast

#### `BulkIngestCfdiUseCase` (Ingestion)
- Wrapper para ingestar múltiples XMLs

---

### ✅ Queries / Reportes

**Única query implementada:** `PostgresMonthlyTaxesQuery`

**Qué hace:**
1. **Stream PUE:** Suma subtotales de facturas PUE activas del mes (solo `rfc_emisor = tenant`)
2. **Stream PPD:** Suma subtotales prorrateados de PaymentApplications vía REPs del mes
3. Combina y **recalcula impuestos con tasas hardcodeadas del 625**

**Resultado:** `MonthlyFiscalSummary` con:
- `collectedIncome` (base gravable)
- `ivaTransferred` (16% de la base)
- `ivaRetained` (8% de la base)
- `isrRetained` (2.1% de la base)
- `calculateNetIvaLiability()` → IVA Trasladado - IVA Retenido - IVA Acreditable (placeholder en 0)

**⚠️ Limitaciones críticas de la query:**
- Solo cuenta facturas **emitidas** (WHERE `rfc_emisor = tenant`)
- **No reporta facturas recibidas** (gastos/compras)
- IVA acreditable = 0 (placeholder)
- Tasas hardcodeadas (no usa los impuestos guardados por factura, porque no se persisten)

---

### ✅ Infraestructura

| Componente | Archivo | Estado |
|------------|---------|--------|
| Repositorio de Facturas | `PostgresInvoiceRepository` | ✅ Implementado (persiste sin impuestos) |
| Repositorio de Complementos | `PostgresPaymentComplementRepository` | ✅ Implementado |
| Modelos Eloquent | `InvoiceModel`, `PaymentComplementModel`, `PaymentApplicationModel` | ✅ Definidos |
| Job de Queue | `ProcessInvoiceJob` | ⚠️ Firma incompatible con UseCase |
| Controlador HTTP | `ImportInvoiceController` | ✅ Endpoint básico |
| Tenant Context | `LaravelTenantContext` | ⚠️ Incompleto (solo `getCurrentRfc()`) |
| Job Dispatcher | `LaravelJobDispatcher` | ⚠️ Leak de dependencia (importa Job de Fiscal) |

---

## 3. Suite de Tests (67 tests, 146 assertions)

| Área | Tests | Assertions | Clasificación |
|------|-------|------------|---------------|
| Fiscal/Domain/Entities | 9 | ~25 | Pure-unit |
| Fiscal/Domain/ValueObjects | 4 | ~10 | Pure-unit |
| Fiscal/Domain/Enums | 2 | ~4 | Pure-unit |
| Fiscal/Domain/Services | 7 | ~15 | Pure-unit |
| Fiscal/Application/UseCases | 7 | ~20 | Pure-unit (mocks) |
| Fiscal/Application/Listeners | 10 | ~20 | Pure-unit (mocks) |
| Fiscal/Infrastructure/Queries | 2 | ~8 | Infrastructure-coupled |
| Ingestion/Application/Services | 12 | ~24 | Pure-unit |
| Ingestion/Application/UseCases | 5 | ~10 | Pure-unit (mocks) |
| Ingestion/Application/DTOs | 5 | ~8 | Pure-unit |
| Shared/Domain/ValueObjects | 3 | ~6 | Pure-unit |

**Test tóxico identificado:** `RealOfficialDataTest` — Imprime STDOUT durante ejecución (reportado como Risky)

**Densidad de mocks:** 17 llamadas a `createMock` en 67 tests — excepcionalmente baja (diseño saludable basado en estado)

---

## 4. Problemas Arquitectónicos Conocidos (FLADP Findings)

| # | Hallazgo | Severidad | Ubicación |
|---|----------|-----------|-----------|
| F008 | Context Leak: Fiscal importa `RawCfdiDto` de Ingestion | High | `ImportInvoiceUseCase.php` |
| F011 | Dependencia inversa: Shared importa `ProcessInvoiceJob` de Fiscal | Critical | `LaravelJobDispatcher.php` |
| F012 | Acoplamiento implícito de tipos en tests (mocks mágicos PHPUnit) | Medium | Suite completa |
| F013 | Tolerancia SAT hardcodeada (2 centavos) | Critical (no modificar) | `Invoice.php`, `PaymentComplement.php` |
| F014 | Zero Data Loss: THIRD_PARTY se preserva pero nunca se procesa | Medium | `IngestAndClassifyCfdiUseCase.php` |
| F015 | Redondeo media-hacia-arriba enforced | Critical (no modificar) | `Regime625TaxStrategy.php` |

---

## 5. Lo que FALTA para el siguiente milestone

### Para filtrar Emitidas vs. Recibidas:
- [ ] Agregar enum `InvoiceDirection` (ISSUED/RECEIVED) al aggregate `Invoice`
- [ ] Persistir dirección en la base de datos
- [ ] Modificar la query mensual para filtrar por dirección

### Para validar retenciones:
- [ ] Persistir impuestos por factura (tabla `invoice_taxes`)
- [ ] Crear query de retenciones cruzadas (emitidas vs. recibidas)

### Para soportar más regímenes:
- [ ] Implementar `Regime601TaxStrategy`, `Regime612TaxStrategy`, etc.
- [ ] Registrar en `TaxStrategyFactory`

### Para procesar Egresos (Notas de Crédito):
- [ ] Crear reglas de clasificación para tipo E
- [ ] Crear listener y flujo fiscal para egresos

### Para procesar REPs (Complementos de Pago):
- [ ] Crear `ImportPaymentComplementUseCase`
- [ ] Crear listener para eventos de tipo P
- [ ] Conectar REP → `Invoice::applyPayment()`
