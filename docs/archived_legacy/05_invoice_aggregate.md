# Step 5: The Invoice Aggregate Root

## Purpose
The `Invoice` class is the Aggregate Root of the Fiscal Core. It is the strict boundary that guarantees the consistency of the fiscal data, ensuring that an invoice can never exist in an illegal state.

## Implementation Details
- **Named Constructor (`createFromIngestion`)**: Replaces the standard constructor to instantly enforce the Cash Flow rule: If an invoice is `PUE` (Pago en una sola exhibición), its balance is instantly set to zero. If it is `PPD` (Pago en parcialidades), its balance equals the total.
- **Protected Behaviors**: Methods like `applyPayment` and `markAsCancelledBySat` enforce strict invariants:
  - You cannot apply a payment to a `LOCKED` invoice.
  - You cannot apply a payment to a `PUE` invoice.
  - A payment cannot exceed the remaining `balanceDue`.

## Impact on Architecture
By locking all mutation logic inside the Aggregate Root, the surrounding Application Services can be very "dumb." They just call `$invoice->applyPayment($money)`, and the Entity itself guarantees that the accounting laws are upheld.
