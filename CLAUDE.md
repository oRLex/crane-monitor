# CraneWatch — notes for coding agents

Monorepo: `api/` (Laravel 13 API) and `web/` (Next.js 15 UI). The web app holds no business logic or authorization; everything comes from the API.

## Commands

```bash
docker compose up -d postgres redis rabbitmq      # pgvector :5433, redis :6390, rabbitmq :5672
cd api && php artisan test                        # PHPUnit, uses the cranewatch_test DB (create it once)
cd api && ./vendor/bin/pint && ./vendor/bin/phpstan
cd api && php artisan ai:eval                     # run before changing prompts, tools, chunking or models
cd web && npm run typecheck && npm test && npm run build
```

## Invariants — don't break these

- **Authorization is scope-based, never role-based.** Check `User::hasScope(Scope::…)` (role ∩ token abilities), or the `scope:` route middleware.
- **Tenant isolation lives in one place:** `BelongsToTenant` global scope + `TenantContext`. Other tenants' records must 404, not 403. New tenant-owned models use the trait; jobs acting for a user wrap work in `TenantContext::runAs()`. `SetTenantContext` must run before `SubstituteBindings` (see `bootstrap/app.php`).
- **Stream URLs** only leave the API with `video:read` (`CraneResource`).
- **AI tools** go through `ToolRegistry::call()` — the only path for the assistant and MCP. A new capability = a `Tool` subclass registered in `AppServiceProvider` (tag `ai.tools`), with `scope()`, `rules()` and a test in `tests/Feature/AiToolsTest.php`. State-changing tools return `mutating(): true` (the assistant then asks the user to confirm).
- **Conversation history is append-only.** Don't trim or rewrite stored messages; it breaks prompt caching and replayed thinking blocks. Volatile context goes into the user turn (`<app_context>`), never into the system prompt.
- **Prompts are versioned** (`Prompts::VERSION`); bump it when changing a prompt — it's part of the report cache key.
- **Numbers in reports come from `ShiftFacts`**, not the model; `ReportSchema::validate()` rejects events not present in the facts.
- Tests run with the mock LLM (`FakeLlmClient`) and hashed embeddings; script exact model responses with `FakeLlmClient::script()`.

## Where things are

See the tree in `README.md`. Decisions and trade-offs: `docs/adr/`.
