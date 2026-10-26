---
paths:
  - "api/app/Ai/**/*.php"
  - "api/app/Mcp/**/*.php"
  - "api/app/Jobs/*.php"
  - "api/resources/evals/**"
  - "api/resources/knowledge/**"
  - "api/config/ai.php"
---

# AI layer

- Models are called only through `App\Ai\Llm\LlmClient`. Never instantiate the Anthropic SDK elsewhere. `FakeLlmClient` must keep supporting every request shape the real client supports (tools, structured output), so tests and the keyless demo keep working.
- Model ids and effort come from `config/ai.php`; don't hardcode them. Default model is `claude-opus-5-5`.
- Prompt caching: system prompt and tool definitions must be byte-stable between requests. No timestamps, ids or user data in `Prompts::*()`; tools are sorted by name in the client. Changing a prompt means bumping `Prompts::VERSION` (see `/prompt-change`).
- History is append-only: never trim, reorder or edit stored `ai_messages`, never strip thinking blocks. Cap conversation length instead.
- Tools: authorization is in the tool (`scope()`), the registry and the tenant scope — never in a prompt. Tool errors return `ToolResult::error()` with a message the model can act on; never throw past the registry. State-changing tools set `mutating(): true`.
- Every LLM or embedding call is recorded via `UsageRecorder` and checked against `TokenBudget` first.
- Anything numeric in model output that users rely on (reports) must be computed beforehand and validated afterwards (`ReportSchema::validate`).
- Retrieval filters by tenant in SQL (`KnowledgeSearch::tenantFilter`). Don't filter results in PHP after the query.
- After changing tools, prompts, chunking, embeddings or the model, run `php artisan ai:eval` and report before/after numbers.
