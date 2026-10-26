---
name: add-ai-tool
description: Add a new capability (tool) for the AI assistant and the MCP server — tool class, scope, validation, registration, tests and eval case. Use when the assistant or MCP clients need to read or change something new.
argument-hint: "<tool_name> <what it does>"
---

# Add an AI tool: $ARGUMENTS

A tool is used by both the in-app assistant and MCP clients through `ToolRegistry::call()`, so one class serves both. Read `api/app/Ai/Tools/GetHourlyStats.php` (read tool) and `AcknowledgeAlarm.php` (mutating tool) as templates.

## Steps

1. **Design first** — decide and state:
   - name (`snake_case`, verb_noun), one-sentence description written for the model (what it returns, when to use it)
   - required `Scope` (reuse existing; a new scope means `app/Domain/Scope.php` + `Role::scopes()` + README role table)
   - read-only or mutating; inputs with limits (e.g. hours 1–24); output shape (small, JSON, no secrets, no other tenants)
2. **Create** `api/app/Ai/Tools/<Name>.php` extending `Tool`:
   - `schema()` with the JSON schema builder (descriptions + min/max), `rules()` with matching Laravel validation
   - `handle()` queries via Eloquent so the tenant scope applies; resolve ids with `findOrFail((string) $input['…'])`
   - reuse `FleetTools` for crane/alarm/point formatting
   - `mutating(): true` if it changes state
3. **Register** in `AppServiceProvider::register()` (the `ai.tools` tag). MCP picks it up automatically via `RegistryTool`.
4. **Mock parity** — if the assistant should reach it in keyless mode, add routing keywords in `FakeLlmClient::route()` and a line in `describe()`.
5. **UI label** — add it to `TOOL_LABELS` in `web/src/components/assistant/assistant-panel.tsx`.
6. **Tests** in `api/tests/Feature/AiToolsTest.php`: works for an allowed role, `Not allowed` without the scope, `Not found` for another tenant's id, invalid input returns an error result. Add an MCP assertion if the tool matters for external clients.
7. **Eval** — add a scenario to `api/resources/evals/assistant.json` that should call the tool.
8. Run `/verify api` and `php artisan ai:eval`; report results.
