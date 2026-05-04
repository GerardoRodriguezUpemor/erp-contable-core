# ADR 0003: Isolating Fiscal Queries via the TenantContextInterface

## Status
Accepted

## Context
In a multi-tenant ERP system, preventing data leakage between different taxpayers (RFCs) is critical. Allowing an RFC to be passed as a simple parameter in Queries or Repositories creates a high risk of spoofing or accidental cross-tenant data access.

## Decision
We introduced a `TenantContextInterface` in the Application layer, implemented via `LaravelTenantContext` in the Infrastructure layer. This context pulls the RFC directly from the trusted authentication session (Auth::user()). We refactored all fiscal queries and repositories to inject this interface and retrieve the RFC internally, rather than accepting it as an external argument.

## Consequences
* **Positive:** Established an impenetrable security boundary at the application level.
* **Positive:** Controllers and external API consumers cannot "spoof" the RFC, as the system only trusts the authenticated context.
* **Positive:** Decouples the Application layer from Laravel's specific authentication mechanisms (Auth facade).
* **Negative:** Slightly complicates unit testing of queries/repositories, as a mock `TenantContextInterface` must now be provided.
