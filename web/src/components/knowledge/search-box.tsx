"use client";

import Link from "next/link";
import { useState } from "react";
import type { Passage } from "@/lib/domain";

/** Retrieval only — shows exactly what the assistant would read, at zero LLM cost. */
export function KnowledgeSearchBox() {
  const [q, setQ] = useState("");
  const [passages, setPassages] = useState<Passage[] | null>(null);
  const [busy, setBusy] = useState(false);

  const search = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!q.trim()) return;
    setBusy(true);
    const res = await fetch(`/api/backend/knowledge/search?q=${encodeURIComponent(q)}`, { headers: { Accept: "application/json" } });
    setPassages(res.ok ? (await res.json()).passages : []);
    setBusy(false);
  };

  return (
    <section className="card p-4">
      <form onSubmit={search} className="flex gap-2">
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search passages, e.g. “parking radius before a storm”"
          className="min-w-0 flex-1 rounded-lg border border-line bg-bg px-3 py-2 text-sm outline-none focus:border-crane"
        />
        <button type="submit" disabled={busy} className="rounded-lg border border-line px-3 py-2 text-sm hover:border-crane disabled:opacity-50">
          {busy ? "Searching…" : "Search"}
        </button>
      </form>
      {passages && (
        <ol className="mt-4 space-y-3">
          {passages.length === 0 && <li className="text-sm text-faint">Nothing found.</li>}
          {passages.map((p) => (
            <li key={p.citation} className="rounded-lg border border-line p-3">
              <div className="mb-1 flex flex-wrap items-center gap-2 text-xs">
                <Link href={`/knowledge/${p.document_id}#s${p.citation.split("§")[1]}`} className="rounded bg-crane/15 px-1 font-mono text-crane">
                  {p.citation}
                </Link>
                <span className="text-ink">{p.document}</span>
                <span className="text-faint">› {p.section}</span>
                <span className="ml-auto text-faint">{p.source}</span>
              </div>
              <p className="line-clamp-3 text-sm text-muted">{p.text}</p>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
