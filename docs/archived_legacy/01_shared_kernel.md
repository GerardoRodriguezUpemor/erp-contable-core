# Step 1: Shared Kernel (Value Objects)

## Purpose
The Shared Kernel defines the fundamental, universally accepted truths of our system. It ensures that data types are strictly enforced from the very beginning.

## Implementation Details
- **`Money` Value Object**: Prevents floating-point corruption by converting everything into integers (cents). `100.50 MXN` is securely stored and calculated as `10050`. It includes `add` and `subtract` methods that always return new immutable instances.
- **`Uuid` Value Object**: Guarantees that any identifier entering the system perfectly matches the SAT UUID regex format, throwing an exception immediately if it fails.

## Impact on Architecture
By placing these in the `Shared` context, both the Ingestion layer and the Fiscal layer can rely on these unbreakable primitives, completely eliminating type-hinting ambiguity and precision leaks across the application.
