# Characterization Safety Net Plan (FLADP Phase 6 Output)

> **Misión Forense:** Congelar el comportamiento heredado (legacy) mediante un arnés de seguridad de pruebas de regresión inflexibles, ANTES de corregir cualquier violación de arquitectura encontrada en el FLADP.

## 1. El Plan de Congelamiento Estructural

### A. Smoke Test Suite: "The Golden Paths"
*Objetivo: Prevenir la destrucción del pipeline asíncrono.*
Dado que descubrimos la regla de "Zero Data Loss" (Finding #014) para los documentos `THIRD_PARTY`, se debe implementar un Smoke Test de Caja Negra extremo (E2E) que envíe un XML basura y certifique que:
1. El archivo termina insertado en `RawCfdiStagingRepository`.
2. Se le asigna la bandera `flagForReview`.
3. El evento se despacha, pero el Listener Fiscal NO levanta un error ni lo procesa.

### B. Characterization Tests: `LaravelJobDispatcher`
*Objetivo: Aislar la violación circular (Shared -> Fiscal).*
Antes de refactorizar la inyección estática de `ProcessInvoiceJob`, debemos escribir un test que:
1. Simule un despacho puro a la fachada de colas (`Queue::fake()`).
2. Confirme que el payload exacto del Job despachado es estrictamente idéntico al actual.
3. Se valide que al ejecutar ese Job, el evento real llega a su destino final.

### C. Regression Harness: Base de Datos Mapeada (PostgresMonthlyTaxesQuery)
*Objetivo: Mitigar el riesgo del Acoplamiento de Entorno (Infrastructure-Coupled).*
Se descubrió que el test `PostgresMonthlyTaxesQueryTest` es en realidad un falso unitario acoplado a variables estáticas de Laravel (`DB::$pueTotal`).
**Red de Seguridad Requerida:** 
Crear un Integration Test real que use una base de datos local temporal (SQLite o Postgres en Docker).
1. Insertar 50 Invoices con combinaciones SAT reales (1 o 2 centavos de redondeo).
2. Insertar facturas canceladas.
3. Ejecutar la Query real contra la BD de prueba y hacer *Snapshot Testing* (guardar el array de salida exacto como un archivo `.json` de snapshot esperado).
Cualquier futuro cambio a la BD deberá empatar el hash de ese JSON.

## 2. Orden Categórico de Refactor (Post-Descubrimiento)

**PROHIBIDO PROCEDER EN ESTE ORDEN:**
Corregir los DTOs -> Corregir Shared -> Cambiar la Base de Datos.

**ORDEN MANDATORIO AUTORIZADO (Para Mitigar Riesgos):**
1. Escribir los 3 Characterization Tests detallados arriba.
2. Extraer `ProcessInvoiceJob` del `Shared/Infrastructure` usando Interfaces y Eventos (Romper Ciclo Crítico).
3. Implementar un Mapper (Anti-Corruption Layer) en el Listener para transformar el `RawCfdiDto` de Ingestion a un array simple fiscal (Romper el Context Leak Fiscal).
4. Liberar el nuevo sistema sin alterar jamás la tolerancia SAT de los 2 centavos (`MAX_DISCREPANCY_TOLERANCE_CENTS`).
