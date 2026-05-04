# Fase 1: Documentación de Pruebas de Dominio (Domain Tests)

## Resumen Arquitectónico
La **Capa de Dominio** es el núcleo fiscal del sistema. Contiene las reglas de negocio puras, exentas de infraestructura, frameworks o persistencia. Los tests en esta fase son **100% aislados y unitarios**, ejecutándose en milisegundos mediante PHPUnit.

Garantizan que las reglas del SAT (tolerancias, ISR, IVA, cancelaciones) sean estrictamente respetadas en memoria antes de siquiera pensar en guardar en la base de datos. Ningún objeto sale del dominio si el modelo es matemáticamente inválido.

---

## 1. Value Objects (Objetos de Valor)
Los tests de los Value Objects garantizan que nuestros "tipos primitivos" son consistentes y libres de pérdidas de datos (como suele ocurrir con los float points).

* **MoneyTest.php**: 
  * Valida la **Inmutabilidad absoluta**: Sumar o restar dinero siempre retorna una nueva instancia.
  * Protege el sistema previendo la corrupción de precisión monetaria trabajando exclusivamente con centavos (enteros).
* **TaxTest.php**: 
  * Garantiza que no se puedan inyectar tasas ni bases impositivas negativas.
  * Aplica los redondeos y establece las limitaciones de base.

## 2. Motor de Impuestos (Tax Strategies & Engine)
Validamos el **Régimen 625 (Plataformas Digitales)**, asegurando que las reglas contables inyecten los márgenes correctos a las facturas.

* **TaxStrategiesTest.php**:
  * **IVA Trasladado (16%)**: Verificación matemática base.
  * **IVA Retenido (8%)**: Verifica retención aplicable para choferes / repartidores.
  * **ISR Retenido**: Corroboración de tasas impositivas establecidas por las tablas de plataformas tecnológicas (ej. 2.1%).
  * **Regla Antifraude**: Un test asegura lógicamente que la suma de "impuestos retenidos" jamás supere la base de los impuestos trasladados, previniendo facturas incoherentes.

## 3. Agregados, Entidades e Invariantes
Es la barrera final. Los "Aggregates" (como la Factura o el REP) coordinan y protegen sus propios datos, lanzando DomainException ante cualquier violación de reglas del SAT.

* **InvoiceTest.php**:
  * **Integridad Matemática y ADR-0001 (Tolerancia SAT)**: Testea que la fórmula Subtotal + Impuestos = Total cuadre rigurosamente, pero de forma inteligente acepta una **discrepancia máxima de .02**, tolerando diferencias de truncamiento reales generadas habitualmente por sistemas externos.
  * **Flujos de Caja (PUE vs PPD)**:
    * **PUE (Pago en una sola exhibición):** Se genera con saldo nulo de inmediato y el dominio bloquea cualquier intento de adjuntarle un Complemento de Pago.
    * **PPD (Pago en parcialidades):** Se genera manteniendo la deuda viva. Decrementa su valor solo según se le apliquen *PaymentComplements*.
  * **Inmutabilidad de Cancelación**: Valida el "Sellado de Estado" previniendo mutaciones, rechazos o abonos sobre una factura que el SAT ya catalogó como CANCELLED.

* **PaymentComplementTest.php & PaymentApplicationTest.php**:
  * Validamos escenarios de **Restricción de Exceso**: El código falla explícitamente (DomainException) si intentamos dispersar abonos que superen al monto original del recibo bancario (mount_received).
  * Comprueba las distribuciones multifactura (relaciones M:N de *Outstanding Balance* vs *Previous Balance*).
