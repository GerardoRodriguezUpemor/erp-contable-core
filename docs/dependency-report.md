# Dependency Intelligence Report (FLADP Phase 4 Output)

> **Evidencia Extraída Estáticamente:** Análisis vía `qossmic/deptrac-shim` (v1.0.2) y `phpstan/phpstan` (v2.1.54).

## 1. Violaciones de Límites Arquitectónicos (Boundary Violations)

### A. Fuga Invertida Crítica: `Shared` -> `Fiscal`
- **Severidad:** Critical
- **Archivo:** `src/Shared/Infrastructure/Queue/LaravelJobDispatcher.php`
- **Evidencia Deptrac:** `App\Shared\Infrastructure\Queue\LaravelJobDispatcher must not depend on App\Fiscal\Infrastructure\Jobs\ProcessInvoiceJob (Fiscal)`
- **Impacto (Blast Radius):** El módulo `Shared` (que supuestamente debe ser agnóstico y proveer solo utilería/interfaces comunes) está instanciando *hardcoded* un Job específico de `Fiscal`. Esto destruye el principio de Inversión de Dependencias. Si se elimina o renombra `ProcessInvoiceJob`, el `Shared` entero se corrompe.

### B. Leak de Dominio: `Fiscal` -> `Ingestion`
- **Severidad:** High
- **Archivos:** `ProcessIncomeCfdiListener.php`, `ImportInvoiceUseCase.php`
- **Evidencia Deptrac:** 
  - `ImportInvoiceUseCase must not depend on RawCfdiDto`
  - `ProcessIncomeCfdiListener must not depend on CfdiOwnershipCategory`
- **Impacto (Blast Radius):** El motor Fiscal requiere constructos del Ingestor. Fiscal no es portátil ni independiente.

---

## 2. Detección de Tipos Ambiguos y Acoplamiento Invisible (PHPStan)

El análisis de PHPStan (Nivel 2) detectó **85 errores** predominantemente en la suite de tests. 
La inmensa mayoría corresponde a *Implicit Type Coupling* en los mocks de PHPUnit (`Call to an undefined method expects()/method()`). 

- **Hipótesis Confirmada:** Al no usar `phpstan-phpunit`, PHPStan expone que los tests asumen los tipos mágicos de PHPUnit, lo cual es normal en Laravel/PHPUnit, pero demuestra un acoplamiento profundo de los tests al *test runner*.
- **Riesgo:** Si se migra a Pest o se actualiza fuertemente PHPUnit (ej. a v14), todos los mocks mágicos colapsarán silenciosamente.

---

## 3. Mapa de Dependencias Prohibidas

```mermaid
graph TD
    classDef danger fill:#ffcccc,stroke:#ff0000,stroke-width:2px;
    classDef warning fill:#ffffcc,stroke:#cccc00,stroke-width:2px;
    classDef safe fill:#ccffcc,stroke:#009900,stroke-width:2px;

    Ingestion[Ingestion Context]:::safe
    Fiscal[Fiscal Context]:::warning
    Shared[Shared Context]:::danger

    Ingestion --> Shared
    Fiscal --> Shared
    
    %% Violaciones
    Shared -.->|Violación Crítica (Jobs)| Fiscal
    Fiscal -.->|Violación Alta (DTOs)| Ingestion
```

## Conclusión Fase 4
La arquitectura tiene un **ciclo de dependencia (Circular Dependency)** oculto:
`Fiscal` -> `Shared` -> `Fiscal`.
Esto significa que el ERP es en la práctica un "Big Ball of Mud" distribuido en carpetas engañosas, congelado por una suite de tests robusta.
