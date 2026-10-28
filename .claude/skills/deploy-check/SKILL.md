---
name: deploy-check
description: Pre-deploy and post-deploy checklist for the API on Wasmer Edge (Supabase, Pusher) and the Vercel web app.
argument-hint: "[pre|post] [api-url] [web-url]"
disable-model-invocation: true
allowed-tools: Bash(curl *) Bash(git status*) Bash(git log*) Bash(gh run *) Bash(gh pr *)
---

# Deploy check: $ARGUMENTS

## Pre-deploy

1. `git status` clean, branch pushed, latest CI run green including `api-wasm32` (`gh run list --limit 3`).
2. New migrations are additive and safe for zero-downtime (no renames/drops of columns still read by the running version).
3. New env vars are listed in `api/.env.example` / `web/.env.example` and in the README Deploy section; remind the user to set them as Wasmer app secrets (`api/deploy/wasmer/wasmer.sh app secrets create`) / in Vercel.
4. Prompt or model changed? Confirm `/ai-eval` was run and numbers are in the PR.

## Post-deploy (given URLs)

1. `curl <api>/api/health` → `{"ok":true}`; `curl <api>/api/auth/users` → 4 users.
2. `curl -I <web>/login` → 200.
3. Ask the user to confirm in the browser: "Live" indicator on Fleet (Pusher keys/cluster on both sides), an assistant answer with a tool call, a shift report reaching `ready` within ~1 min (Wasmer `queue` job running).
4. If something fails, map it with the troubleshooting table in README → Deploy and say which Wasmer / Supabase / Pusher / Vercel setting to check. Never change production settings yourself.
