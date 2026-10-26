---
paths:
  - "api/tests/**/*.php"
  - "web/tests/**/*.ts"
---

# Tests

- API feature tests extend `Tests\TestCase` (RefreshDatabase + seeded demo data). Authenticate with `$this->as(self::VIEWER)` / `tokenFor()` so real Sanctum abilities are exercised; don't use `actingAs()` for authorization tests.
- Any new endpoint or tool gets at least: allowed role succeeds, role without the scope gets 403/`Not allowed`, other tenant gets 404/`Not found`.
- AI tests never hit the network: the mock LLM and hashed embeddings are active (keys are blanked in `phpunit.xml`). Script model behaviour with `FakeLlmClient::script(...)` and call `FakeLlmClient::reset()` in `setUp()`.
- Use `Event::fake()` when a test triggers broadcasts; use `Queue::fake()` + calling the job when you need to assert the failure path.
- Web tests (Vitest) cover pure logic (`src/lib/**`): reducers, parsers, helpers. Keep them DOM-free.
