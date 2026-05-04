# Architecture Decision Records (ADRs) Log

Este directorio sirve como el **Log de Auditoría de Arquitectura** (Auditory Log). A diferencia de una carpeta `auditory/` genérica, los proyectos de software avanzados con DDD utilizan **ADRs** para llevar un registro histórico inmutable de *por qué* se tomaron decisiones de diseño importantes.

Cualquier nuevo estándar técnico, desviación de frameworks, o regla fiscal mayor debe registrase aquí antes de programarse.

## Índice de Declaraciones Arquitectónicas

| ADR | Decisión | Propósito / Beneficio |
| :--- | :--- | :--- |
| **0001** | [SAT Rounding Tolerance](0001-sat-rounding-tolerance-in-fiscal-core.md) | Define la permisividad matemática (\<= $0.02) en el core domain para mitigar rechazos de XML válidos por truncation de terceros. |
| **0002** | [Wipe and Replace Strategy](0002-wipe-and-replace-strategy-for-payment-applications.md) | Asegura idempotencia en la ingesta y cancelación de pagos. Evita inconsistencias de M:N sobre facturas (REPs). |
| **0003** | [Tenant Context Interface](0003-isolating-fiscal-queries-via-the-tenant-context-interface.md) | Aísla a nivel de CQRS que las consultas fiscales respeten la tenencia M:N sin filtrar en el Application layer. |
| **0004** | [JSON Polymorphism in Audit Logs](0004-using-json-polymorphism-for-the-fiscal-audit-logs.md) | **[Auditoría Blanda]**: Establece que todo registro en `FiscalAuditLogs` use payloads JSON polimórficos, para soportar cambios en el esquema SAT sin romper auditorías pasadas. |
| **0005** | [Strict CamelCase Table Naming](0005-enforcing-strict-camelcase-naming-conventions-for-database-tables.md) | Exige empatar la DDL de PostgreSQL con los sistemas legacy *upstream*, forzando uso estricto de `CamelCase` a expensas de la convención de Eloquent/Laravel. |

### ¿Cómo agregar un nuevo ADR?
1. Copia el formato estándar usando Markdown.
2. Nómbralo secuencialmente (ej. `0006-nuevo-motor-cache.md`).
3. Detalla el Contexto, la Decisión y las Consecuencias.
4. Regístralo en esta tabla.