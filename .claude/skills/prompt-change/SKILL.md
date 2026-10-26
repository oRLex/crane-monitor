---
name: prompt-change
description: Safely change an LLM prompt or tool description — version bump, cache-safety check, eval before/after. Use whenever editing api/app/Ai/Assistant/Prompts.php, a tool description(), or the report schema.
---

# Changing a prompt or tool description

Prompts and tool definitions are part of the cached prefix and of the report cache key, so changes must be deliberate.

1. **Baseline:** run `/ai-eval` (or `php artisan ai:eval`) before editing and keep the numbers.
2. **Edit** with these constraints:
   - no timestamps, ids, user names or per-request data in system prompts or tool descriptions (breaks caching — volatile context goes into `<app_context>` in the user turn)
   - say what to do, not what not to do; keep safety rules (no bypassing limiters, cite only returned ids, don't claim mutating actions are done)
   - tool descriptions say what the tool returns and when to use it, in one or two sentences
3. **Bump** `Prompts::VERSION` (format `YYYY-MM-DD.N`). This invalidates cached shift reports on purpose.
4. **Re-run** the eval; compare. With a real key, also run 2–3 conversations through the UI and check `ai_usage.cache_read_tokens` is non-zero on repeated requests.
5. **Record** the before/after numbers and the reason in the commit message.
