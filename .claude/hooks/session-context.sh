#!/usr/bin/env bash
# SessionStart: tell Claude which local services are up, so it doesn't guess.
set -uo pipefail
api="$(curl -s -m 2 localhost:8000/api/health >/dev/null && echo up || echo down)"
web="$(curl -s -m 2 -o /dev/null localhost:3000/login && echo up || echo down)"
db="$(docker compose -f "${CLAUDE_PROJECT_DIR:-.}/docker-compose.yml" ps --status running --services 2>/dev/null | tr '\n' ' ')"
branch="$(git -C "${CLAUDE_PROJECT_DIR:-.}" branch --show-current 2>/dev/null)"
jq -n --arg c "Local stack: API ${api} (:8000), web ${web} (:3000), docker: ${db:-none}. Branch: ${branch}. Start/stop with /dev-stack." \
  '{hookSpecificOutput: {hookEventName: "SessionStart", additionalContext: $c}}'
