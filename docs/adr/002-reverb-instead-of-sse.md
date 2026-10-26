# 002 — Reverb (WebSockets) for real-time, not SSE from PHP

**Context.** The old app streamed SSE from a Node route. In PHP, a long-lived SSE response holds a worker per open tab.

**Decision.** The telemetry consumer broadcasts to Reverb (Pusher protocol, managed on Laravel Cloud) on private channels `tenant.{id}` and `platform`. Channel auth reuses the same scope + tenant rules as REST. The client gets a REST snapshot on every (re)connect, because channels don't replay missed events.

**Exception.** The assistant does stream SSE from PHP: it's one short-lived response per question (seconds), bounded by the agent-loop limit, not a connection per open tab.

**Consequences.** One more service locally (`reverb:start`). Broadcasts are batched per tick and tenant, not per point.
