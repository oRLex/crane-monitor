---
paths:
  - "api/database/**/*.php"
---

# Database

- PostgreSQL only (pgvector, `tsvector`, `jsonb`, `percentile_cont` are used). Don't add SQLite/MySQL fallbacks.
- Never edit a migration that is already committed — add a new one. (A hook blocks edits to committed migrations.)
- New tenant-owned tables get an indexed `tenant_id` column; foreign keys to `tenants`/`cranes` where the row can't outlive its parent.
- Vector columns and indexes are created with raw SQL (`vector(1024)`, HNSW `vector_cosine_ops`). The dimension must match `config('ai.embeddings.dimensions')`.
- Seeders are idempotent (`updateOrCreate`, guards) because Laravel Cloud runs `db:seed --force` on every deploy.
