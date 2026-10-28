# Deploying the API to Wasmer Edge

Cloud setup (all on free tiers): **Wasmer Edge** (API, queue worker and telemetry as cron jobs) ·
**Supabase** (Postgres + pgvector; also cache and queue) · **Pusher Channels** (WebSockets) ·
**Vercel** (web). Why, and what it costs us: [ADR 006](../../../docs/adr/006-wasmer-supabase-pusher.md).

| File | Purpose |
| --- | --- |
| `api/wasmer.toml` | Wasmer package: `php/php` runtime, `run` (HTTP via `php -S`) and `artisan` commands |
| `api/app.yaml` | Wasmer app: non-secret env, health check, jobs (`migrate`, `queue`, `telemetry`) |
| `router.php` | Router for PHP's built-in server |
| `Dockerfile`, `wasmer.sh` | Wasmer CLI in Docker (no darwin-amd64 build exists) + clean bundle build |
| `.github/workflows/deploy-api.yml` | Deploys after green CI on `master` |

`wasmer.sh run|deploy` copies tracked and untracked-not-ignored files (never `.env`) and runs
`composer install --no-dev` inside the container, then calls `wasmer`.

## The runtime (`php/php@8.3.403`)

- PHP 8.3.4, **32-bit**: epoch-ms values don't fit `int` (use `App\Support\EpochMs`), `crc32()` can be
  negative. CI job `api-wasm32` runs the whole suite on this runtime; locally: `wasmer.sh test`.
- Has `pdo_pgsql`, `curl`, `openssl`, `mbstring`, `intl`, `bcmath`. **No** `redis`, `sockets`, `pcntl`.
- `"php/php" = "=8.3.4"` (Wasmer's Laravel guide) is an older build **without** `pdo_pgsql`.
- No long-running processes: requests and jobs only (180 s per job by default).

| Piece | Here |
| --- | --- |
| Cache, queue, live telemetry window | Supabase Postgres (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`) |
| Queue worker | `queue` job every minute: `queue:work --stop-when-empty` (jobs start within ~1 min) |
| Telemetry | `telemetry` job every minute: 11 ticks × 5 s, in-process (`TELEMETRY_BUS=sync`); no RabbitMQ |
| WebSockets | Pusher Channels (`BROADCAST_CONNECTION=pusher`); the web's Reverb client speaks the same protocol |

## One-time setup

1. **Supabase** → new project → *Connect* → **Session pooler** connection string (IPv4; the direct
   host is IPv6-only and the transaction pooler breaks prepared statements). Append `?sslmode=require`.
   The `vector` extension is created by the migration.
2. **Pusher** → Channels → new app (Sandbox plan), pick a cluster (e.g. `eu`). Note app id, key, secret.
3. **Wasmer** → account; access token at https://wasmer.io/settings/access-tokens.
4. Log in, create the app with a first deploy, set secrets, deploy again (from `api/`):

```bash
export WASMER_OWNER=<namespace>
deploy/wasmer/wasmer.sh login
deploy/wasmer/wasmer.sh deploy    # creates the app; its `migrate` job fails until DB_URL is set
```

Then set the secrets in the Wasmer dashboard (app → Settings → Secrets), or with the CLI
(values typed on the command line end up in shell history):

```bash
s() { deploy/wasmer/wasmer.sh app secrets create --app "$WASMER_OWNER/cranewatch-api" "$1" "$2"; }
s APP_KEY "$(php artisan key:generate --show)"
s APP_URL https://<app>.wasmer.app                          # the URL the first deploy printed
s FRONTEND_URL https://<web>.vercel.app
s DB_URL "postgresql://postgres.<ref>:<password>@aws-0-<region>.pooler.supabase.com:5432/postgres?sslmode=require"
s PUSHER_APP_ID ...; s PUSHER_APP_KEY ...; s PUSHER_APP_SECRET ...; s PUSHER_APP_CLUSTER eu
s ANTHROPIC_API_KEY ...   # optional: mock LLM without it
s VOYAGE_API_KEY ...      # optional: hashed embeddings without it
deploy/wasmer/wasmer.sh deploy
```

The pre-deployment `migrate` job runs on every deploy.

5. **Seed and index once**, from your machine against Supabase: put the same `DB_URL` into
   `deploy/wasmer/local.env` (gitignored; template: `local.env.example`), then

```bash
deploy/wasmer/wasmer.sh run . --net --env-file /local.env --command artisan -- db:seed --force
deploy/wasmer/wasmer.sh run . --net --env-file /local.env --command artisan -- knowledge:ingest --sync
```

6. **Vercel** → root directory `web`, env: `API_URL=https://<api>/api`,
   `NEXT_PUBLIC_REVERB_KEY=<pusher key>`, `NEXT_PUBLIC_REVERB_HOST=ws-<cluster>.pusher.com`,
   `NEXT_PUBLIC_REVERB_PORT=443`, `NEXT_PUBLIC_REVERB_SCHEME=https`.
7. **GitHub** → repository variable `WASMER_OWNER`, secret `WASMER_TOKEN`. From then on every green
   CI run on `master` deploys the API; Vercel deploys the web through its Git integration.

Then `/deploy-check post <api-url> <web-url>`.

## Local check against a local database

```bash
cp deploy/wasmer/local.env.example deploy/wasmer/local.env   # set APP_KEY; DB_* → a pgvector DB
deploy/wasmer/wasmer.sh run . --net --env-file /local.env --command artisan -- migrate --force --seed
deploy/wasmer/wasmer.sh run . --net --env-file /local.env          # http://localhost:8080/api/health
```

From the container, the host is `host.docker.internal`.

## Free-tier limits to keep in mind

| Service | Limit | Our usage |
| --- | --- | --- |
| Pusher Sandbox | 200k messages/day, 100 connections | per 5 s tick: 1 message to each tenant viewer, 2 to each platform (admin/operator) viewer → 17–35k/day per open tab |
| Supabase Free | 500 MB DB, pauses after 7 days without activity | the cron jobs keep it active |
| Wasmer Edge | see your plan | 2 cron jobs per minute + requests |
