# Step 7: Payment Complements & Cash Flow Integration

## Purpose
To comply with Mexican tax laws (specifically Regime 625), taxes are triggered by cash flow, not just invoicing. When an invoice is `PPD` (Pago en Parcialidades o Diferido), the tax liability is only recognized when a Payment Complement (REP) is issued.

## Implementation Details
- **The M:N Reality**: Modeled using the `PaymentApplication` intermediate entity. This represents the "Documento Relacionado" in the SAT XML, linking a REP to a specific invoice installment (`Parcialidad`).
- **`PaymentComplement` Aggregate Root**: Orchestrates the incoming payments.
- **Tolerance & Integrity**: 
  - `addApplication()`: Dynamically guards the boundaries, ensuring applications don't exceed the received total plus a `MAX_DISCREPANCY_TOLERANCE_CENTS` (2 cents).
  - `verifyTotalIntegrity()`: A deferred invariant checked pre-persistence. It calculates the absolute difference between applied cents and received cents, forgiving mathematical truncation (up to 2 cents) while strictly protecting the accounting integrity from real discrepancies.

## Impact on Architecture
This completes the Fiscal Core by ensuring the system is strictly bound by accounting laws but sufficiently flexible to survive real-world SAT XML truncations and rounding anomalies.
