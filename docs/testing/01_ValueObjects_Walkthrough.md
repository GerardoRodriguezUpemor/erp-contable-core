# Domain Tests Walkthrough: Value Objects & Invariants

Esta fase de la documentación describe la ejecución técnica y los "Whys" arquitectónicos de nuestras Pruebas de Dominio (Phase 1). Siguiendo las directrices del proyecto y el modelado de un dominio rico, estas pruebas son ejecutadas **100% aisladas**, lo que significa que **no requieren levantar bases de datos, Redis, ni servicios como XAMPP o Docker containers**.

## 1. Value Objects: Dinero Matemáticamente Seguro (`MoneyTest`)

El Value Object `Money` sirve para proteger a toda la aplicación de los clásicos errores matemáticos de punto flotante en PHP (flaws in IEEE 754 float arithmetic).

### Tests Implementados
*   **`test_it_can_add_two_money_objects_immutably`**: 
    Demuestra que `Money::add()` no muta la instancia original, protegiendo efectos secundarios al operar sobre valores en agregados de facturas.
*   **`test_it_throws_exception_on_negative_initialization`** & **`test_it_throws_exception_when_subtraction_results_in_negative`**: 
    Blindaje contra balances negativos. Si un proceso de resta arroja -1 centavo, el Value Object aborta la ejecución con un `DomainException`.

### Ejecución Esperada
Se pueden correr desde consola directamente con PHPUnit:
```bash
./vendor/bin/phpunit tests/Shared/Domain/ValueObjects/MoneyTest.php
```

## 2. Reglas del motor fiscal: Value Object `Tax` (`TaxTest`)

El Objeto de Valor `Tax` es más sofisticado ya que encapsula las estrictas reglas dictadas por el SAT en torno a tasas, categorías y **Tolerancias de Redondeo**.

### Tests Implementados
*   **`test_it_rejects_negative_amounts`** y **`test_it_rejects_negative_rates`**: 
    Previene instanciar un impuesto (incluso Retenido) con montos negativos. La "resta" la define la `TaxCategory`, no el valor en sí.
*   **`test_it_enforces_sat_rounding_tolerance_with_base_amount`**: 
    Este test es el corazón matemático que demuestra el ADR-0001. Acepta diferencias entre `(Base Gravable * Tasa)` y `Monto Reportado (amount)` si y solo si la diferencia es igual o inferior a 2 centavos ($0.02 o 2.01 Cents).

### ¿Por qué no usamos Tinker para esto?
Herramientas interactivas como `tinker` de Laravel son excelentes para depurar Eloquent Query Builders o despachar Jobs temporales de infraestructura. Sin embargo, nuestras pruebas unitarias definen **contratos formales** de la lógica de dominio. Queremos que un CI/CD o GitHub Action pueda fallar si alguien en el futuro altera la tolerancia permitida. No requerimos inicializar el Kernel de Laravel para correrlas, haciendo que ejecuten en ~15 milisegundos.