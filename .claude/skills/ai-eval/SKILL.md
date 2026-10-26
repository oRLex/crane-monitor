---
name: ai-eval
description: Run the AI golden-set evaluation (retrieval recall@k/MRR and assistant scenarios) and compare with the previous run. Use after changing prompts, tools, chunking, embeddings or the model, or when asked about AI quality.
argument-hint: "[retrieval|assistant|all]"
allowed-tools: Bash(php artisan ai:eval*) Bash(php artisan knowledge:ingest*)
---

# AI eval

Mode: !`grep -qE '^ANTHROPIC_API_KEY=.+' "${CLAUDE_PROJECT_DIR}/api/.env" && echo "real model (costs money)" || echo "mock LLM (pipeline check only)"`

1. If chunking/embeddings changed, re-index first: `cd api && php artisan knowledge:ingest --sync --force`.
2. Run `cd api && php artisan ai:eval --suite=<suite>` where the suite is `$ARGUMENTS`, or `all` if empty.
   - With a real API key the assistant suite calls the model; tell the user before running it and report the printed cost.
3. Compare with the last numbers you can find (previous output in this conversation, PR description or commit message). Report:
   - retrieval recall@3 and MRR (CI gate: recall ≥ 0.8)
   - assistant pass count and every failing scenario with its problem column
4. For a failure, say whether it's a regression from the change or pre-existing, and propose the smallest fix. Never edit the golden files to make a run pass unless the expectation itself was wrong — then say so explicitly.
