# Test Taxonomy Report (Real Classification)

> **Evidencia base recolectada mediante análisis estricto de STDOUT, I/O y llamadas de frameworks en la Fase 2 del FLADP.**

## Matriz de Severidad de Clasificación
- **Pure-Unit:** 64 tests
- **Infrastructure-Coupled:** 1 test
- **Behavioral-Characterization:** 2 tests (congelan estado legado)

---

## Hallazgos de Taxonomía

### 1. `Infrastructure-Coupled` (Acoplamiento de Entorno)
**Archivo:** `tests\Fiscal\Infrastructure\Queries\PostgresMonthlyTaxesQueryTest.php`
**Clasificación:** `infrastructure-coupled`
**Evidencia:** Uso intenso de `\Illuminate\Support\Facades\DB`.
**Extracto de Evidencia Cruda:**
```php
$res->collected_subtotal = \Illuminate\Support\Facades\DB::$pueTotal;
DB::flush();
$pueQueryLog = DB::$queryLog[0];
```
**Conclusión:** Este test finge ser unitario pero en realidad acopla el dominio a las abstracciones globales de Laravel (Facades). El uso de propiedades estáticas inyectadas en la Facade es peligroso y altamente acoplado.

### 2. `Behavioral-Characterization` (Congelamiento de Bugs/Cálculos Legacy)
**Archivo:** `tests/Fiscal/Domain/Entities/RealOfficialDataTest.php`
**Clasificación:** `behavioral-characterization` / `toxic`
**Evidencia:** Imprime STDOUT directamente durante ejecución (`=== RESULTADO DE TU DOCUMENTO OFICIAL ===`).
**Conclusión:** Este test no prueba comportamiento arquitectónico aislado. Fue diseñado como un *script ejecutable* que congela un resultado de cálculo exacto (un CFDI de Régimen 625) contra una expectativa visual.

### 3. `Pure-Unit` (Módulos Aislados)
**Archivos:** Mayoría de la suite en `tests/Shared`, `tests/Fiscal/Domain`, `tests/Ingestion/Application/Services`
**Clasificación:** `pure-unit`
**Evidencia:** 
- Densidad de Mocks total de la suite: **17 llamadas** a constructores de *Mocking* (`createMock`). Esto es un indicador **excepcionalmente saludable** (baja densidad de mocks implica bajo acoplamiento en el diseño del dominio base).
- 0 usos de `file_get_contents`, `fopen`, o llamadas al disco.

---

## Análisis de Toxicidad y Riesgo (Fase 2 Output)
- **God Class Fakes:** Ninguna identificada en la capa de persistencia (los `Repositories` en memoria están limpios y no abusan de dependencias).
- **Abuso de Mocks:** Ausente. El modelo de dominio (`Invoice`, `Money`, `Tax`) funciona predominantemente basado en estado, no en comportamiento mockeado.
- **Riesgo:** Solo la capa de consultas (`PostgresMonthlyTaxesQueryTest`) está atada al estado estático del Framework, lo cual limitaría una futura portabilidad pura.
