# ERP Contable - Core Fiscal Engine

## Resumen del Sistema (Estado Real - Mayo 2026)
ERP Contable es un motor fiscal estricto basado en **Domain-Driven Design (DDD)**. Su arquitectura ha sido validada mediante un proceso de **Ingeniería Inversa Forense (FLADP)**, el cual extrajo la realidad ejecutable del sistema basándose en sus tests y comportamiento en tiempo de ejecución, descartando diseños teóricos obsoletos.

El sistema funciona como un monolito altamente cohesivo en PHP puro (con adaptadores de infraestructura para Laravel).

## Capacidades Actuales
* **Ingesta y Clasificación:** Validación XSD, parseo y clasificación jerárquica de propiedad (9 reglas incluyendo emisor, receptor, nómina, REP y traslados).
* **Procesamiento de Ingresos (Régimen 625):** Cálculo automático de IVA trasladado (16%), IVA retenido (8%) e ISR retenido (2.1%).
* **Integridad SAT estricta:** Tolerancia dura de 2 centavos para discrepancias de redondeo del emisor.
* **Control de Flujo de Efectivo:** Manejo automático de reglas PUE (cobro inmediato) vs. PPD (pendiente de REP).
* **Zero Data Loss:** Documentos no reconocidos (Third Party) se preservan en *staging* y se marcan para revisión sin detener colas asíncronas.

## Documentación Oficial (Fuente de Verdad)

Toda la documentación teórica original ha sido archivada en `docs/archived_legacy/`. La documentación actual refleja **exclusivamente el código existente y probado**:

### 1. Estado Actual y Blueprint
* [Análisis de Estado Actual](docs/current-state-analysis.md) - *Lectura obligatoria. Qué hace el sistema hoy y qué gaps tiene (Ej. falta filtrar emitidas vs recibidas).*
* [Extensibility Blueprint](docs/extensibility-blueprint.md) - *Cómo agregar nuevos regímenes y soportar otras entidades.*

### 2. Auditoría Forense Arquitectónica (FLADP Reports)
Estos documentos fueron generados automáticamente analizando el código en ejecución, dependencias y asserts de los tests:
* [Mapa de Arquitectura Real](docs/architecture-map.md) - *Diagrama de secuencia exacto y context leaks detectados.*
* [Reglas de Negocio Ocultas](docs/hidden-business-rules.md) - *Tolerancias de centavos y reglas de fallback.*
* [Taxonomía de Tests](docs/test-taxonomy.md) - *Clasificación de los tests (Pure-unit vs Infrastructure-coupled).*
* [Reporte de Dependencias](docs/dependency-report.md) - *Violaciones detectadas entre Bounded Contexts.*
* [Evaluación de Riesgos](docs/risk-assessment.md) - *Hotspots arquitectónicos que no deben tocarse sin pruebas.*
* [Plan de Caracterización](docs/characterization-plan.md) - *Red de seguridad requerida para futuros refactors.*

## Stack Tecnológico
* **Lenguaje:** PHP 8.5+
* **Framework:** Laravel (exclusivamente como adaptador de persistencia y colas)
* **Testing:** PHPUnit 13.1 (Con banderas estrictas de failOnRisky)
* **Análisis Estático:** PHPStan y Deptrac

## Siguiente Milestone
El desarrollo actual se centra en implementar el **Filtrado Fiscal (Emitidas, Recibidas, Retenciones Cruzadas)** para soportar reportes completos de ingresos, egresos e IVA acreditable. Ver plan de implementación activo en la bitácora del proyecto.