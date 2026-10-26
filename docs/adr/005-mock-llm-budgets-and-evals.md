# 005 — Mock LLM, budgets, usage ledger and evals

**Context.** AI features have to be demoable and testable without paid keys, affordable in production, and changeable without guessing whether quality got worse.

**Decision.**
- `LlmClient` interface with `AnthropicLlmClient` and a deterministic `FakeLlmClient` (used without a key and in tests). The fake routes on keywords to the real tools, so authorization, persistence, streaming and validation are exercised end to end; tests can script exact responses.
- Cost: prompt caching on the stable prefix (system + sorted tool definitions), low effort for chat, numbers computed in SQL, report reuse by input hash, retrieval-only search in the UI.
- Control: a usage ledger row per call (tokens incl. cache reads/writes, cost, latency, stop reason), a daily token budget per tenant checked before each call, per-user rate limit on AI endpoints.
- Quality: `php artisan ai:eval` — retrieval recall@k / MRR (offline, gated in CI) and assistant scenarios (expected tools, citations, forbidden content incl. other tenants' data and safety bypasses). With a real key the same suite measures the actual model and prints its cost.

**Trade-off.** The mock's answers are templated; the assistant eval in mock mode checks the pipeline, not language quality. Run it with a key before shipping prompt or model changes.
