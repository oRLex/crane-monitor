# 004 — One ToolRegistry for the assistant and the MCP server

**Context.** The in-app assistant (Claude tool use) and external agents (MCP) need the same capabilities. Two implementations would drift — and authorization drift is how data leaks.

**Decision.** Each capability is one `Tool` class: schema (Laravel JSON Schema builder), validation rules, required scope, `mutating()` flag, handler. `ToolRegistry::call()` checks the scope, validates input, runs the handler inside the caller's tenant context, logs it, and turns failures into error results the model can recover from. The assistant and the MCP adapter (`RegistryTool`) both call it.

**Rules that follow.**
- Authorization never depends on the prompt. Unauthorized tools aren't advertised, and are rejected if called anyway.
- Not found and other-tenant look the same ("Not found").
- Mutating tools: in the assistant they only produce a confirmation button; MCP clients get the `readOnlyHint: false` annotation and their own approval flow.
