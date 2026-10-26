#!/usr/bin/env bash
# Local dev stack for CraneWatch. Usage: stack.sh up|down|status
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
LOGS="${TMPDIR:-/tmp}/cw"
PROCS='artisan (serve|reverb:start|horizon|telemetry:consume|telemetry:simulate)'

status() {
  pgrep -fl "$PROCS|next dev" | sed 's/^[0-9]* //' | grep -oE 'artisan [a-z:]+|next dev' | sort -u
  printf 'api:  %s\n' "$(curl -s -m 3 localhost:8000/api/health || echo down)"
  printf 'web:  %s\n' "$(curl -s -m 5 -o /dev/null -w '%{http_code}' localhost:3000/login || echo down)"
}

case "${1:-status}" in
  up)
    docker info >/dev/null 2>&1 || { echo "Docker is not running"; exit 1; }
    (cd "$ROOT" && docker compose up -d postgres redis rabbitmq) >/dev/null
    mkdir -p "$LOGS"
    if pgrep -f "$PROCS" >/dev/null; then echo "already running"; status; exit 0; fi
    cd "$ROOT/api"
    PHP_CLI_SERVER_WORKERS=6 nohup php artisan serve --port=8000 >"$LOGS/api.log" 2>&1 &
    nohup php artisan reverb:start --port=8080 >"$LOGS/reverb.log" 2>&1 &
    nohup php artisan horizon >"$LOGS/horizon.log" 2>&1 &
    nohup php artisan telemetry:consume >"$LOGS/consume.log" 2>&1 &
    sleep 2
    nohup php artisan telemetry:simulate >"$LOGS/sim.log" 2>&1 &
    cd "$ROOT/web" && nohup npm run dev >"$LOGS/web.log" 2>&1 &
    sleep 10
    status
    ;;
  down)
    pkill -f "$PROCS"; pkill -f "next dev"; sleep 1
    echo "stopped"
    ;;
  status) status ;;
  *) echo "usage: stack.sh up|down|status"; exit 2 ;;
esac
