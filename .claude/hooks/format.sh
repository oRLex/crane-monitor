#!/usr/bin/env bash
# PostToolUse(Edit|Write): keep PHP formatted with Pint so diffs stay clean.
set -uo pipefail
file="$(jq -r '.tool_input.file_path // empty')"
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
case "$file" in
  "$root"/api/*.php)
    [ -x "$root/api/vendor/bin/pint" ] && (cd "$root/api" && ./vendor/bin/pint -q "$file") || true ;;
esac
exit 0
