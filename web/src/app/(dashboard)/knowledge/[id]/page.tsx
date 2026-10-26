import Link from "next/link";
import { Markdown } from "@/components/assistant/markdown";
import { api } from "@/lib/api/server";

type Props = { params: Promise<{ id: string }> };

interface Doc {
  document: { id: number; title: string; content: string; tenant_id: string | null; status: string };
  chunks: { ordinal: number; heading: string }[];
}

export default async function DocumentPage({ params }: Props) {
  const { id } = await params;
  const { document, chunks } = await api<Doc>(`/knowledge/documents/${encodeURIComponent(id)}`);
  // Render section by section so citations [D{id}§{n}] can link to an anchor.
  const sections = splitSections(document.content);

  return (
    <article className="mx-auto max-w-3xl space-y-4">
      <nav className="text-sm text-muted">
        <Link href="/knowledge" className="hover:text-ink">
          ← Knowledge
        </Link>
      </nav>
      <h1 className="text-2xl font-semibold">{document.title}</h1>
      <p className="text-xs text-faint">
        {document.tenant_id ? "Customer document" : "Platform library"} · {chunks.length} indexed passages
      </p>
      {sections.map((s, i) => (
        <section key={i} className="card scroll-mt-20 p-4 target:border-crane" id={anchorFor(chunks, s.heading)}>
          {s.heading && <h2 className="mb-2 font-semibold">{s.heading}</h2>}
          <Markdown text={s.body} />
        </section>
      ))}
    </article>
  );
}

function splitSections(md: string) {
  const out: { heading: string; body: string }[] = [];
  let current = { heading: "", body: "" };
  for (const line of md.split("\n")) {
    const h = /^#{2,4}\s+(.*)$/.exec(line);
    if (/^#\s/.test(line)) continue;
    if (h) {
      if (current.body.trim() || current.heading) out.push(current);
      current = { heading: (h[1] ?? "").trim(), body: "" };
    } else current.body += line + "\n";
  }
  out.push(current);
  return out.filter((s) => s.body.trim());
}

/** First chunk whose heading path ends with this section heading -> "s{ordinal}". */
function anchorFor(chunks: { ordinal: number; heading: string }[], heading: string) {
  const c = chunks.find((c) => c.heading === heading || c.heading.endsWith(` > ${heading}`)) ?? (heading ? undefined : chunks[0]);
  return c ? `s${c.ordinal}` : undefined;
}
