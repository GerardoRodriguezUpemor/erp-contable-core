# Developer Onboarding Guide

Welcome to the ERPContable Fiscal Engine. Because this system deals directly with strict regulatory APIs and exact financial mathematics, we have rigid development guidelines.

## 1. Local Environment Setup

We rely on Docker and standard Laravel auxiliary tools via `Sail` or native composer scripts.

```bash
# 1. Clone & install dependencies
git clone git@github.com:your-org/erpcontable.git
cd erpcontable
composer install

# 2. Environment variables
cp .env.example .env
php artisan key:generate

# 3. Spin up infrastructure (Postgres, Redis)
docker-compose up -d

# 4. Run Migrations
php artisan migrate
```

## 2. Directory Structure Conventions

You will not find Domain logic in `app/Models` or `app/Http/Controllers`.
Look strictly inside `src/`.

* `src/Fiscal/Domain`: (Zero framework dependencies) Entities, Enums, Value Objects.
* `src/Fiscal/Application`: Use Cases and CQRS interfaces.
* `src/Fiscal/Infrastructure`: Laravel-specific implementations, Repositories, DB Queries.
* `src/Fiscal/Presentation`: Controllers and CLI commands.
* `src/Shared`: Tenant constraints, event dispatching contracts.

## 3. Creating a New Feature

If you are asked to introduce a new SAT Tax concept (e.g., *Impuesto sobre Hospedaje*):

1. **Create the Value Object / Strategy:** Go to `src/Fiscal/Domain/Services/TaxStrategies` and implement the math.
2. **Write Unit Tests:** Ensure you add tests under `tests/Fiscal/Domain/Services/` proving the calculations across rounding edge cases.
3. **Register the Strategy:** Bind it in the `TaxStrategyFactory`.
4. **Update Enums:** Add it to `TaxCategory` or `TaxType` as necessary.

## 4. Coding Standards & Rules

* **Strict Types:** Every file must declare `declare(strict_types=1);`.
* **Immutability:** Value Objects like `Money` or `Tax` must never have setters. They return new instances.
* **No `DB::` facades in Domain:** Ensure all database communication goes through Repository interfaces injected into the Application layer.
* **Polymorphism in Audit Logs:** Database auditing uses strict JSON schemas. See ADR `0004-using-json-polymorphism-for-the-fiscal-audit-logs.md`.
* **Database Naming:** Follow `CamelCase` structure for database tables exactly as requested by legacy systems. (ADR `0005`).