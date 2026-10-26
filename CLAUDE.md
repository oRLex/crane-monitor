@AGENTS.md

## Claude Code specifics

- Area rules load automatically from `.claude/rules/` when you touch matching files.
- Procedures are skills: `/verify`, `/add-ai-tool`, `/add-api-endpoint`, `/ai-eval`, `/prompt-change`, `/dev-stack`, `/deploy-check`.
- Reviewers: delegate to `tenant-security-reviewer` after changes to auth, tenancy, controllers, tools or migrations; to `ai-quality-reviewer` after changes under `api/app/Ai`, `api/app/Mcp` or prompts.
- MCP servers in `.mcp.json`: `cranewatch` (this app's own tools, as the operator demo user) and `laravel-boost` (schema, logs, docs, tinker). Prefer them over ad-hoc scripts when inspecting data.
- Hooks in `.claude/settings.json` format PHP with Pint after edits and block edits to secrets, lock files and already-committed migrations.
