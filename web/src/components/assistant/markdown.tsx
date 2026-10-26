import Link from "next/link";
import { Fragment } from "react";
import { splitCitations } from "@/lib/assistant/citations";

type Block = { kind: "p" | "ul" | "ol" | "quote"; lines: string[] };

/**
 * Deliberately tiny Markdown subset (paragraphs, lists, quotes, **bold**, `code`, _em_,
 * citations) rendered as React elements — model output never goes through innerHTML.
 */
export function Markdown({ text }: { text: string }) {
  const blocks: Block[] = [];
  for (const line of text.split("\n")) {
    if (line.trim() === "") {
      blocks.push({ kind: "p", lines: [] });
      continue;
    }
    const kind: Block["kind"] = /^\s*[-*]\s+/.test(line) ? "ul" : /^\s*\d+\.\s+/.test(line) ? "ol" : /^>\s?/.test(line) ? "quote" : "p";
    const content = line.replace(/^\s*[-*]\s+|^\s*\d+\.\s+|^>\s?/, "");
    const prev = blocks.at(-1);
    if (prev && prev.kind === kind && prev.lines.length > 0) prev.lines.push(content);
    else blocks.push({ kind, lines: [content] });
  }

  return (
    <div className="space-y-2 text-sm leading-relaxed">
      {blocks
        .filter((b) => b.lines.length > 0)
        .map((b, i) => {
          if (b.kind === "ul" || b.kind === "ol") {
            const List = b.kind;
            return (
              <List key={i} className={`space-y-1 pl-5 ${b.kind === "ul" ? "list-disc" : "list-decimal"}`}>
                {b.lines.map((l, j) => (
                  <li key={j}>
                    <Inline text={l} />
                  </li>
                ))}
              </List>
            );
          }
          if (b.kind === "quote") {
            return (
              <blockquote key={i} className="space-y-1 border-l-2 border-crane/50 pl-3 text-muted">
                {b.lines.map((l, j) => (
                  <p key={j}>
                    <Inline text={l} />
                  </p>
                ))}
              </blockquote>
            );
          }
          return (
            <p key={i}>
              {b.lines.map((l, j) => (
                <Fragment key={j}>
                  {j > 0 && <br />}
                  <Inline text={l} />
                </Fragment>
              ))}
            </p>
          );
        })}
    </div>
  );
}

function Inline({ text }: { text: string }) {
  return (
    <>
      {splitCitations(text).map((s, i) =>
        s.type === "cite" ? (
          <Link
            key={i}
            href={`/knowledge/${s.doc}#s${s.section}`}
            className="mx-0.5 rounded bg-crane/15 px-1 font-mono text-[11px] text-crane hover:bg-crane/25"
            title="Open the cited passage"
          >
            {s.label.slice(1, -1)}
          </Link>
        ) : (
          <Fragment key={i}>{emphasis(s.text)}</Fragment>
        ),
      )}
    </>
  );
}

function emphasis(text: string) {
  return text.split(/(\*\*[^*]+\*\*|`[^`]+`|_[^_]+_)/g).map((p, i) => {
    if (p.startsWith("**") && p.endsWith("**") && p.length > 4) return <strong key={i}>{p.slice(2, -2)}</strong>;
    if (p.startsWith("`") && p.endsWith("`") && p.length > 2)
      return (
        <code key={i} className="rounded bg-surface-2 px-1 font-mono text-[12px]">
          {p.slice(1, -1)}
        </code>
      );
    if (p.startsWith("_") && p.endsWith("_") && p.length > 2)
      return (
        <em key={i} className="text-muted">
          {p.slice(1, -1)}
        </em>
      );
    return p;
  });
}
