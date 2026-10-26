---
name: add-api-endpoint
description: Add a tenant-scoped REST endpoint to the Laravel API and expose it to the Next.js app, with scope checks and the role-matrix tests. Use for any new route the web app or clients need.
argument-hint: "<METHOD /path> <purpose>"
---

# Add an API endpoint: $ARGUMENTS

## API

1. Route in `api/routes/api.php` inside the `auth:sanctum` + `tenant` group, with an explicit `->middleware('scope:<scope>')`. Expensive/LLM endpoints also get `throttle:ai`.
2. Controller method: `$request->validate([...])`, Eloquent queries (tenant scope applies), `findOrFail((string) $id)` for ids, response in the existing wire format (camelCase, epoch ms where telemetry-like). Field-level data (like `streamUrl`) checks its own scope.
3. Tests in `api/tests/Feature/` using `$this->as(...)`: allowed role 2xx, missing scope 403, other tenant 404, invalid input 422.

## Web

4. Server-side use: call `api<T>('/path')` from a server component or action in `web/src/lib/actions.ts`; types in `web/src/lib/domain.ts`.
5. Client-side use only if needed: add the path to the `ALLOWED` regex in `web/src/app/api/backend/[...path]/route.ts` and fetch `/api/backend/<path>`.

## Done

Run `/verify all`. Then delegate a review to the `tenant-security-reviewer` subagent with the diff.
