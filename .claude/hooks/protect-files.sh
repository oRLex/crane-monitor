#!/usr/bin/env bash
# PreToolUse(Edit|Write): block edits that must never be done by hand.
# - secrets (.env files)            -> never touched by agents
# - lock files                      -> change via composer/npm, not by editing
# - migrations already committed    -> add a new migration instead
set -uo pipefail
input="$(cat)"
file="$(jq -r '.tool_input.file_path // empty' <<<"$input")"
[ -z "$file" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
rel="${file#"$root"/}"

deny() {
  jq -n --arg r "$1" '{hookSpecificOutput: {hookEventName: "PreToolUse", permissionDecision: "deny", permissionDecisionReason: $r}}'
  exit 0
}

case "$rel" in
  *.env|*.env.*|*/.env|*/.env.*)
    [[ "$rel" == *.env.example ]] || deny "Secrets file ($rel): edit .env.example instead and ask the user to update their local env." ;;
  */composer.lock|*/package-lock.json|composer.lock|package-lock.json)
    deny "Lock file ($rel): change dependencies with composer/npm commands, not by editing the lock file." ;;
  api/database/migrations/*.php)
    if git -C "$root" ls-files --error-unmatch "$rel" >/dev/null 2>&1; then
      deny "Migration $rel is already committed. Create a new migration (php artisan make:migration) instead of editing it."
    fi ;;
esac
exit 0
