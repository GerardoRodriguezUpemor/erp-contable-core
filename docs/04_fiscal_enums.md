# Step 4: Fiscal Enums (The State Machines)

## Purpose
In the Fiscal Core, string assignments (like `$status = 'active'`) are dangerous. Enums lock down the vocabulary of our domain, ensuring that state transitions are perfectly predictable.

## Implementation Details
- **`SatStatus` Enum**: Tracks the official status of the invoice as recognized by the SAT (`ACTIVE`, `CANCELLED`, `PENDING_CANCELLATION`).
- **`LifecycleState` Enum**: Tracks our internal business logic state (`IMPORTED`, `PROCESSED`, `DECLARED`, `LOCKED`).

## Impact on Architecture
These Enums define the "State Machine" of an invoice. By strictly typing these states, our Application layer cannot accidentally put the system into an illegal state (e.g., trying to set a status to `'DELETED'` when that concept doesn't exist fiscally).
