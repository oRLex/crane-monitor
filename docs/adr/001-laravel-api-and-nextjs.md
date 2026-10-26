# 001 — Laravel API on Laravel Cloud, Next.js on Vercel

**Context.** The first version was a Next.js monolith (in-memory/Prisma data, in-process SSE hub). The product needs background workers (telemetry consumer, AI jobs), a queue, a broker and a real authorization layer — things a serverless frontend host is bad at.

**Decision.** Business logic, data and authorization move to a Laravel API; Next.js stays as the UI. The browser never calls the API directly: server components call it with the user's Sanctum token, and a same-origin route (`/api/backend/*`) proxies the few client-side calls (assistant stream, snapshot, Reverb channel auth). The token lives in an httpOnly cookie.

**Why tokens, not Sanctum's cookie SPA mode.** `*.vercel.app` and `*.laravel.cloud` don't share a registrable domain, so first-party session cookies across them don't work. Bearer tokens with abilities also give MCP clients the same, narrower-able credential.

**Consequences.** No CORS. Two deploys. The proxy is allow-listed by path and adds nothing but the user's own token.
