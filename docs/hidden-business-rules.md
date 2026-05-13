# Hidden Fiscal Behavioral Discovery (FLADP Phase 5 Output)

> **Evidencia Extraída:** Expresiones regulares sobre STDOUT, aserciones matemáticas en `tests/Fiscal/Domain` y rastreo de Side-Effects en `tests/Ingestion`.

## 1. Tolerancias SAT (Rounding & Precision)

**Hallazgo:** El sistema implementa una tolerancia fiscal global estricta de **2 centavos** (`MAX_DISCREPANCY_TOLERANCE_CENTS = 2`).
- **Comportamiento Probado:** Si el XML declara impuestos o totales que difieren del cálculo matemático perfecto de la base por el porcentaje aplicable (ej. 16% de IVA) en 1 o 2 centavos, el sistema **perdona la discrepancia** y acepta el documento (`InvoiceTaxIntegrationTest::test_it_forgives_minor_sat_rounding_discrepancies`).
- **Regla Implícita:** Si la diferencia es > 2 centavos, lanza una excepción de dominio irremediable (`"Irrecoverable fiscal discrepancy"`), rehusando persistir el Aggregate para proteger los libros contables.

## 2. Reglas Fiscales Implícitas (Rejection Paths & Fallbacks)

**Hallazgo:** Tratamiento de comprobantes de Terceros (`THIRD_PARTY`).
- **Comportamiento Probado:** Cuando el clasificador no puede asociar ni el RFC emisor ni el receptor al Tenant actual, lo marca como `THIRD_PARTY`. 
- **Regla Oculta Crítica:** Estos XMLs **NO son ignorados ni rechazados**.
  1. Se persisten en la tabla de Staging (`stagingRepository->persist()`).
  2. Se les añade una bandera de revisión manual (`stagingRepository->flagForReview()`).
  3. **Generan un evento de integración** (`ClassifiedCfdiIngestedIntegrationEvent`).
- **Conclusión:** El sistema preserva la integridad absoluta (Zero Data Loss) sobre todo archivo ingerido. Sin embargo, el contexto `Fiscal` rechaza silenciosamente estos eventos en su *Listener* (`ProcessIncomeCfdiListenerTest::test_it_ignores_third_party_events`). Por lo tanto, quedan huérfanos de procesamiento automático, a la espera de intervención humana o de otro contexto futuro (ej. `Audit`).

## 3. Reglas de Cálculo en Estrategias de Impuestos (Regimen 625)
- El redondeo aritmético en PHP es de media-hacia-arriba (`round()`) a nivel de centavos exactos (`(int) round($baseCents * RATE)`). 
- Los impuestos se calculan estrictamente de la *Base Cents*, y la lógica de negocio prohíbe explícitamente tasas negativas o que el impuesto retenido supere al trasladado.

---

## Matriz de Blast Radius de Reglas de Negocio

| Regla | Implementación Actual | Impacto si se altera |
|-------|-----------------------|----------------------|
| **2 Cent Tolerance** | Hardcodeada en `Invoice` y `PaymentComplement` | **Critical:** Reducirla a 0 centavos rechazará miles de CFDIs reales del SAT con error de truncamiento del emisor. Aumentarla permitirá contabilidad descuadrada. |
| **Third Party Flagging** | `IngestAndClassifyCfdiUseCase::execute` | **High:** Si el Ingestor lanza una excepción, se bloquea el procesamiento en batch de zips masivos por culpa de un CFDI basura. Debe mantenerse silencioso y resiliente. |
