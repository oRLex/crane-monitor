# 006 — Production on free tiers: Wasmer Edge, Supabase, Pusher

**Context.** The API was planned for Laravel Cloud (ADR 001–003: managed Postgres, Redis, Reverb, Horizon and long-running consumers). We want a fully cloud deployment that costs nothing at demo scale. Wasmer Edge hosts PHP, but as WebAssembly: PHP 8.3 is 32-bit, has no `redis`, `sockets` or `pcntl`, and runs only requests and scheduled jobs (≤ 180 s by default), with no long-running processes.

**Decision.**
- API on Wasmer Edge (`api/wasmer.toml`, `api/app.yaml`); `php/php` pinned to 8.3.403, the first build with `pdo_pgsql`.
- Postgres + pgvector on Supabase, through the IPv4 session pooler (`DB_URL`). Cache and queue use the `database` drivers in the same database: no extra service, and the Wasmer runtime can't talk to Redis anyway.
- Queue worker: a per-minute Wasmer job, `queue:work --stop-when-empty`. Telemetry: a per-minute job running `telemetry:simulate` for 11 ticks of 5 s on the sync bus. Bounded runs flush their hourly aggregates on exit.
- WebSockets on Pusher Channels. Reverb speaks the Pusher protocol, so the web client and channel auth are unchanged; only env differs.
- The code stays 32-bit-safe: epoch-ms values are floats (`App\Support\EpochMs`, exact to 2^53, same JSON). The PRNG works on 16-bit halves and the hash embeddings read bits explicitly, so results match 64-bit bit for bit. CI job `api-wasm32` runs the suite on the Wasmer runtime.
- The API deploys from GitHub Actions after green CI on `master`; Vercel deploys the web.

**Alternatives.** Upstash QStash (push-based queue, no worker) would start jobs immediately, but needs a custom queue driver and a signed endpoint, and long LLM jobs would run into Wasmer's HTTP timeout. SQS still needs the same cron worker. A container host (Cloud Run, Render) would avoid the 32-bit and process constraints, but not on Wasmer.

**Consequences.**
- Jobs start up to ~1 minute late (reports, document ingestion), and the queue polls Postgres.
- The simulator restarts every minute, so crane state (phase, position) jumps at minute boundaries. Alarms and aggregates are rebuilt from the DB and stay consistent. A real deployment would feed telemetry from outside (an MQTT bridge or HTTP ingestion) rather than simulate it on the API host.
- RabbitMQ, Horizon and Reverb remain in the local stack only (ADR 002/003 still describe them).
- Free-tier ceilings: Pusher Sandbox 200k messages/day (hence 5 s ticks); Supabase pauses inactive free projects, but the cron jobs keep it active.
- New code must not keep epoch ms or 32-bit hashes in `int`; `api-wasm32` catches it.
