# Session Notes: Fiscal Tax Deductions Analysis & Test Harness

**Rama:** `fix-erp-tax-deductions`  
**Fecha:** 2026-05-25  
**Participantes:** Gerardo (arquitecto fiscal) + Antigravity (agente IA)

---

## Contexto de la Sesión

Esta sesión tuvo dos objetivos simultáneos:

1. **Análisis fiscal real**: Revisar y corregir los cálculos contables del periodo agosto–octubre 2025 de Jonathan bajo el Régimen 625 (Plataformas Tecnológicas), detectando errores en cómo el ERP maneja los egresos.
2. **Diseño arquitectónico**: Definir la nueva arquitectura de sub-dominios del módulo `Fiscal` para soportar correctamente deducciones, activos fijos e impuestos.

---

## Parte 1: Correcciones al Análisis Fiscal (Régimen 625)

Se identificaron tres errores críticos en el análisis previo del ERP:

### ❌ Error 1: Vehículo como gasto directo (ISR)
El ERP calculaba la deducción del Vento ($175,375) directamente en el mes de compra para ISR. **Esto es incorrecto.**

**Regla correcta (Art. 34, fracción VI LISR):**
- Un vehículo es una **inversión (activo fijo)**, no un gasto.
- Se deduce mediante **depreciación del 25% anual**, prorrateado mensualmente.
- **Tope de deducción: $175,000 MXN** (Art. 36, fracción II LISR). Todo lo que exceda ese tope es no deducible.
- Excepción: vehículos eléctricos/híbridos tienen un tope de $250,000 MXN.

**Implicación para el ERP:** El sistema necesita una entidad `FixedAsset` separada del CFDI original que calcule la cuota de depreciación mensual.

### ✅ Correcto: IVA del vehículo sí se acredita al 100% en el mes de compra
A diferencia del ISR, el IVA pagado por la compra del vehículo ($28,060) **sí se acredita totalmente** en el mes en que se efectuó el pago (Art. 5, fracción I LIVA). El cálculo del ERP del saldo a favor de IVA ($27,652.03) es aritméticamente correcto.

### ❌ Error 2: No valida forma de pago en combustible
El ERP no verifica si la gasolina ($1,980.09) fue pagada en efectivo o con tarjeta.

**Regla crítica (Art. 27, fracción III LISR):** El combustible pagado **en efectivo es 100% no deducible**, sin importar el monto. Debe pagarse con tarjeta, transferencia, cheque nominativo o monedero electrónico.

### ❌ Error 3: No distingue Pagos Provisionales vs. Definitivos
Si Jonathan optó por retenciones como **Pago Definitivo** (Art. 113-B LISR), la ley prohíbe aplicar cualquier deducción de gastos o acreditamiento de IVA. El ERP no valida esta condición antes de calcular.

---

## Parte 2: Diseño Arquitectónico Acordado

### Nueva Estructura de Sub-Dominios en `src/Fiscal/`

Se decidió dividir el módulo `Fiscal` en tres sub-dominios especializados:

```
src/Fiscal/
├── Sales/          → Ventas emitidas: ingresos acumulables y retenciones sufridas
├── Expenses/       → Gastos recibidos: deducciones autorizadas y acreditamiento IVA  
└── Assets/         → Activos fijos: inversiones, topes legales y depreciación
```

### Adiós al "God Service" (`ImportInvoiceUseCase`)

El caso de uso actual trata **todas** las facturas (ventas y gastos) igual. Se reemplazará por:
- `ImportSaleUseCase` — procesa facturas emitidas (ingresos)
- `ImportExpenseUseCase` — procesa facturas recibidas (gastos)
- `RegisterAssetUseCase` — se dispara cuando el evaluador detecta una inversión

### `FiscalOutcome` como Enum de dominio

El `DeductionEvaluator` no devuelve un booleano sino un resultado tipado:

```php
enum FiscalOutcome {
    case FULLY_DEDUCTIBLE;
    case PARTIALLY_DEDUCTIBLE;
    case NON_DEDUCTIBLE;        // ej: gasolina en efectivo
    case FIXED_ASSET;           // ej: automóvil → genera FixedAsset
    case REQUIRES_MANUAL_REVIEW;
}
```

Esto elimina `if` gigantes en las capas superiores y hace la intención del negocio explícita.

### `FixedAsset` es una entidad autónoma

El CFDI de compra es solo **evidencia de adquisición**. Una vez que se crea el `FixedAsset`, este vive independientemente con su propio ciclo de vida:
- Depreciación mensual prorrateada
- Baja / venta / pérdida
- Revaluación
- Reemplazo

El CFDI original ya no importa para el cálculo mensual de impuestos.

### Router: `ProcessIncomeCfdiListener`

El listener actual no distingue entre ventas y gastos. Se convertirá en un router explícito:
- `INCOME_ISSUED` → `ImportSaleUseCase`
- `INCOME_RECEIVED` → `ImportExpenseUseCase`

---

## Parte 3: Tests Añadidos/Corregidos

Se ejecutaron los 67 tests existentes (todos en verde) y se añadieron **27 tests nuevos** para un total de **94 tests / 198 assertions**.

### 🔴 → ✅ `RealOfficialDataTest.php` (corregido)

Estaba marcado como **Risky** por PHPUnit porque imprimía a STDOUT con `echo`. Se eliminaron todos los `echo` y se sustituyeron por aserciones concretas que pinean el cálculo oficial del Régimen 625.

### ✅ `InvoiceLifecycleTest.php` (nuevo — 8 tests)

Cubre el estado **LOCKED** del ciclo de vida de `Invoice` (facturas incluidas en declaración anual):
- No se pueden aplicar pagos a una factura LOCKED
- No se pueden agregar impuestos a una factura LOCKED
- No se pueden revertir pagos en una factura LOCKED
- El SAT cancelar una LOCKED dispara excepción (requiere intervención humana)
- Avance de `IMPORTED → PROCESSED` funciona correctamente
- `PROCESSED` no puede avanzar a sí mismo
- La cancelación emite un `InvoiceCancelledEvent` exactamente una vez
- Los eventos se limpian después de ser liberados (`releaseEvents()`)

### ✅ `Regime625RoundingEdgeCasesTest.php` (nuevo — 6 tests)

Pinea el comportamiento de redondeo half-up (`round()`) identificado como regla crítica F015 en el FLADP. Cualquier cambio al redondeo romperá estos tests:
- Base $1.00: ISR redondea de 2.1 → 2 centavos
- Base $0.50: ISR redondea de 1.05 → 1 centavo
- Base $3.33: Valida los tres taxes simultáneamente
- La estrategia siempre devuelve exactamente 3 taxes
- Siempre hay 1 trasladado y 2 retenidos
- Regla antifraude: retenidos nunca superan trasladados (en 4 bases distintas)

### ✅ `TaxStrategyFactoryBoundaryTest.php` (nuevo — 8 tests)

Pinea las fronteras exactas de años soportados (2024–2026). Si en 2027 no se agrega `Regime625TaxStrategy2027`, estos tests **fallarán en producción** — ese es exactamente el comportamiento deseado para detectar el gap:
- Años válidos: 2024, 2025, 2026
- Años inválidos: 2023 (antes), 2027 (después)
- Regímenes desconocidos: 601, 612, string vacío

### ✅ `ProcessIncomeCfdiListenerRoutingTest.php` (nuevo — 4 tests)

**Tests de caracterización** que documentan explícitamente el gap arquitectónico actual: hoy `INCOME_ISSUED` e `INCOME_RECEIVED` van al mismo `ImportInvoiceUseCase`. Estos tests tienen comentarios que indican que **deben borrarse y reemplazarse** cuando se implemente el router Sales/Expenses:
- Ambas categorías llaman al mismo use case (comportamiento actual documentado)
- El régimen se pasa correctamente desde `TenantContext`
- Funciona con regímenes distintos al 625
- Las 7 categorías no-income se descartan silenciosamente (consolidado en un solo test)

---

## Estado Actual vs. Siguiente Paso

| Estado | Detalle |
|--------|---------|
| ✅ Análisis fiscal revisado | Errores de depreciación, combustible y Pagos Definitivos identificados |
| ✅ Arquitectura acordada | Sales / Expenses / Assets + FiscalOutcome + FixedAsset autónomo |
| ✅ Suite de tests reforzada | 94 tests / 198 assertions / 0 Risky |
| ⏳ Pendiente | Implementar los nuevos sub-dominios (requiere aprobación del plan) |

> **Nota:** La implementación de los nuevos sub-dominios está diseñada para ser **aditiva**: nuevos listeners, nuevas clases, sin romper el pipeline de Ingestion ni las facturas de venta existentes.
