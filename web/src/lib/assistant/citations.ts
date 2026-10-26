/** Splits assistant text into plain text and citation tokens like [D3§2]. */
export type Segment = { type: "text"; text: string } | { type: "cite"; doc: number; section: number; label: string };

export function splitCitations(text: string): Segment[] {
  const out: Segment[] = [];
  let last = 0;
  for (const m of text.matchAll(/\[D(\d+)§(\d+)\]/g)) {
    if (m.index > last) out.push({ type: "text", text: text.slice(last, m.index) });
    out.push({ type: "cite", doc: Number(m[1]), section: Number(m[2]), label: m[0] });
    last = m.index + m[0].length;
  }
  if (last < text.length) out.push({ type: "text", text: text.slice(last) });
  return out;
}
