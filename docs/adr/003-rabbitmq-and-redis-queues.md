# 003 — RabbitMQ for the telemetry stream, Redis/Horizon for jobs

**Context.** Two kinds of asynchronous work with different shapes: a continuous stream of small, perishable messages from cranes; and occasional, slow, retryable jobs (embedding documents, generating reports).

**Decision.**
- Telemetry: RabbitMQ topic exchange `telemetry`, routing key `crane.{id}` — the shape an MQTT bridge or IoT hub would publish with. Messages expire after 30 s (stale telemetry is useless); the consumer acks in batches per tick. Other consumers (archival, analytics) can bind their own queues without touching the producer.
- Jobs: Laravel queues on Redis with Horizon. A separate `ai` queue so a slow LLM provider can't starve normal jobs; retries/backoff are on the jobs.
- `TELEMETRY_BUS=sync` runs without a broker (dev, tests, a minimal deploy).

**Consequences.** RabbitMQ isn't managed on Laravel Cloud (use CloudAMQP or the sync bus). The consumer is single-instance by design (it caches open alarms in memory); scaling out would need a per-crane partition (consistent-hash exchange) or moving that state to Redis.
