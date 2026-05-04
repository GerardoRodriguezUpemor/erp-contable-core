# ADR 0001: SAT Rounding Tolerance in Fiscal Core

## Status
Accepted

## Context
When parsing third-party SAT XMLs (CFDIs), the stated `total` often differs from our strict internal mathematical calculation by 1 or 2 cents. This is due to varying floating-point truncation algorithms used by different PACs (Authorized Certification Providers) per concept line.

Initially, our `Invoice` Aggregate Root demanded exact mathematical parity (0 cents difference). This caused a 30% rejection rate of legally valid, real-world invoices.

## Decision
We implemented a Domain-level tolerance constant (`MAX_DISCREPANCY_TOLERANCE_CENTS = 2`) inside the `Invoice` and `PaymentComplement` aggregates. We evaluate the absolute difference between the parsed total and the strictly calculated total. 

## Consequences
* **Positive:** The system seamlessly ingests real-world XMLs without crashing or flagging false-positive fiscal errors.
* **Positive:** Mathematical integrity is still strictly enforced for any discrepancy > $0.02 MXN.
* **Negative:** Our internal general ledger will carry a micro-variance of 1-2 cents per document compared to the raw math of the subtotals, which must be absorbed into a "Rounding Differences" accounting account at the end of the fiscal year.
