---
paths:
  - "api/app/**/*.php"
  - "api/routes/**/*.php"
  - "api/config/**/*.php"
---

# Laravel API

- Controllers stay thin: validate with `$request->validate()`, query through Eloquent (the tenant scope applies), return arrays/resources. Business logic goes into `app/Telemetry`, `app/Ai` or a dedicated service.
- Every route under `auth:sanctum` also has `tenant` and an explicit `scope:` middleware. Don't add a route without a scope.
- Tenant-owned models use `BelongsToTenant` and carry a `tenant_id` column (denormalise it if needed — e.g. `alarms.tenant_id`). Never bypass with `withoutGlobalScopes()` in request code; it's allowed only in jobs/commands that re-establish context (`TenantContext::runAs`).
- Resolve ids from user input with `Model::query()->findOrFail((string) $id)` so other tenants' ids 404.
- Wire format is camelCase with epoch-ms timestamps for telemetry/alarms (`Alarm::toWire()`); keep it stable — the web app and broadcasts depend on it.
- Config lives in `config/*.php`; never call `env()` outside config files.
- PHPStan level 8 must pass without new ignores or baseline entries. Fix types with real PHPDoc shapes (`array{…}`, `list<…>`), not casts or `@var` overrides.
- Long-running commands (`telemetry:*`) must be restart-safe: idempotent writes, no state that can't be rebuilt from the DB.
