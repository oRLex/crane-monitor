# CraneWatch — instructions for coding agents

Tool-agnostic project instructions (Claude Code reads them via `CLAUDE.md`; other agents read this file directly).

Monorepo: `api/` is a Laravel 13 API (PHP 8.3+), `web/` is a Next.js 15 UI. The web app holds no business logic or authorization; all data and access decisions come from the API.

## Commands

```bash
docker compose up -d postgres redis rabbitmq      # pgvector :5433, redis :6390, rabbitmq :5672
cd api && php artisan test                        # PHPUnit on the cranewatch_test DB
cd api && ./vendor/bin/pint && ./vendor/bin/phpstan analyse --memory-limit=1G
cd api && php artisan ai:eval                     # before changing prompts, tools, chunking or models
cd web && npm run typecheck && npm test && npm run build
```

A change is done when the checks for the side you touched pass. Report failures with their output; don't skip or weaken a check to make it pass.

## Invariants

- **Authorization is scope-based, never role-based.** Check `User::hasScope(Scope::…)` (role ∩ token abilities) or use the `scope:` route middleware.
- **Tenant isolation lives in one place:** the `BelongsToTenant` global scope + `TenantContext`. Another tenant's record is a 404, never a 403. Jobs acting for a user wrap their work in `TenantContext::runAs()`.
- **Stream URLs** leave the API only with `video:read` (`CraneResource`).
- **AI tools** run only through `ToolRegistry::call()` — the single path for the assistant and MCP.
- **Conversation history is append-only.** Volatile context goes into the user turn (`<app_context>`), never into the system prompt.
- **Numbers in reports come from SQL** (`ShiftFacts`), never from the model.
- **Secrets:** never read, print or commit `.env`, `.env.docker`, API keys or tokens.

## Workflow

- Plan first for anything touching auth, tenancy, migrations or the AI layer; keep diffs small and focused.
- New behaviour comes with a test; bug fixes come with a test that fails before the fix.
- Decisions with trade-offs get an ADR in `docs/adr/` (next free number, same format).
- Commit messages: imperative subject ≤ 72 chars, body explains why.

## Layout

See the tree in `README.md`. Area-specific rules are in `.claude/rules/`, procedures in `.claude/skills/`.
