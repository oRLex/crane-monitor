---
name: verify
description: Run the project's quality gates (Pint, PHPStan, PHPUnit, TypeScript, Vitest, Next build) for the parts of the repo that changed, and report results. Use before saying a change is done, before committing, or when asked to check/verify the code.
argument-hint: "[api|web|all]"
allowed-tools: Bash(./vendor/bin/pint*) Bash(./vendor/bin/phpstan*) Bash(php artisan test*) Bash(npm run typecheck*) Bash(npm test*) Bash(npm run build*) Bash(git status*) Bash(git diff*)
---

# Verify

Changed files:

!`git status --short`

Scope: `$ARGUMENTS` (if empty, infer from the changed files above: `api/**` → api, `web/**` → web, both → all).

## api (run from `api/`)

1. `./vendor/bin/pint --test` — if it fails, run `./vendor/bin/pint` and mention the files it fixed.
2. `./vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — fix real type errors; never add ignores or baseline entries.
3. `php artisan test` — needs `docker compose up -d postgres` and the `cranewatch_test` database.

## web (run from `web/`)

1. `npm run typecheck`
2. `npm test`
3. `npm run build` — only for scope `web`/`all` when routes, config or server code changed.

## Report

One line per gate: ✓ or ✗ with the first relevant error. If something can't run (e.g. Postgres is down), say so explicitly — that's "not verified", not "passed".
