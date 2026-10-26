---
name: dev-stack
description: Start, stop or check the full local stack (Docker infra, Laravel API, Reverb, Horizon, telemetry simulator/consumer, Next.js dev server).
argument-hint: "up|down|status"
disable-model-invocation: true
allowed-tools: Bash(${CLAUDE_SKILL_DIR}/scripts/*)
---

# Local stack: $ARGUMENTS

Run `${CLAUDE_SKILL_DIR}/scripts/stack.sh $ARGUMENTS` (`up`, `down` or `status`; default `status`).

- `up` starts Docker infra (postgres, redis, rabbitmq) if needed, then the API (:8000), Reverb (:8080), Horizon, `telemetry:consume`, `telemetry:simulate` and `next dev` (:3000). Logs go to `$TMPDIR/cw/*.log`.
- `down` stops the app processes (Docker containers keep running).
- `status` shows which processes are up and checks `/api/health` and `/login`.

After `up`, report the URLs and the demo users from README. If a health check fails, show the last lines of the matching log.
