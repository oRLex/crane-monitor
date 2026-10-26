"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { acknowledgeAlarm } from "@/lib/actions";
import { useAssistant, type Part } from "./assistant-provider";
import { Markdown } from "./markdown";

const TOOL_LABELS: Record<string, string> = {
  list_cranes: "Fleet status",
  get_crane_status: "Crane status",
  get_hourly_stats: "Hourly stats",
  list_alarms: "Alarms",
  search_docs: "Documentation",
  acknowledge_alarm: "Acknowledge alarm",
};

const SUGGESTIONS = [
  "Give me a fleet overview",
  "Which cranes had overload alarms in the last 12 hours?",
  "At what wind speed must we stop lifting?",
];

export function AssistantPanel() {
  const a = useAssistant();
  const [draft, setDraft] = useState("");
  const [history, setHistory] = useState<{ id: string; title: string }[] | null>(null);
  const scroller = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scroller.current?.scrollTo({ top: scroller.current.scrollHeight, behavior: "smooth" });
  }, [a.items]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && a.close();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [a]);

  if (!a.isOpen) return null;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (a.busy || !draft.trim()) return;
    void a.send(draft);
    setDraft("");
  };

  const showHistory = async () => {
    const res = await fetch("/api/backend/assistant/conversations", { headers: { Accept: "application/json" } });
    if (res.ok) setHistory((await res.json()).conversations);
  };

  return (
    <aside
      aria-label="AI assistant"
      className="fixed inset-y-0 right-0 z-30 flex w-full max-w-[440px] flex-col border-l border-line bg-surface shadow-2xl"
    >
      <header className="flex items-center gap-2 border-b border-line px-4 py-3">
        <div className="min-w-0 flex-1">
          <h2 className="font-semibold">Assistant</h2>
          <p className="truncate text-xs text-muted">{a.focus?.title ?? "Live data, alarms and documentation"}</p>
        </div>
        <button type="button" onClick={showHistory} className="rounded-md px-2 py-1 text-xs text-muted hover:text-ink">
          History
        </button>
        <button type="button" onClick={a.newChat} className="rounded-md px-2 py-1 text-xs text-muted hover:text-ink">
          New
        </button>
        <button type="button" onClick={a.close} aria-label="Close assistant" className="rounded-md px-2 py-1 text-muted hover:text-ink">
          ✕
        </button>
      </header>

      {history && (
        <div className="max-h-60 overflow-y-auto border-b border-line bg-surface-2 text-sm">
          {history.length === 0 && <p className="px-4 py-3 text-faint">No conversations yet</p>}
          {history.map((c) => (
            <button
              key={c.id}
              type="button"
              className="block w-full truncate px-4 py-2 text-left hover:bg-surface"
              onClick={() => {
                setHistory(null);
                void a.load(c.id);
              }}
            >
              {c.title}
            </button>
          ))}
        </div>
      )}

      <div ref={scroller} className="flex-1 space-y-4 overflow-y-auto px-4 py-4" aria-live="polite">
        {a.items.length === 0 && (
          <div className="space-y-2">
            <p className="text-sm text-muted">Ask about live cranes, alarms, a shift, or a procedure. Answers cite the documentation they rely on.</p>
            {SUGGESTIONS.map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => void a.send(s)}
                className="block w-full rounded-lg border border-line px-3 py-2 text-left text-sm hover:border-crane"
              >
                {s}
              </button>
            ))}
          </div>
        )}
        {a.items.map((item, i) =>
          item.role === "user" ? (
            <div key={i} className="ml-8 rounded-lg bg-surface-2 px-3 py-2 text-sm">
              {item.text}
            </div>
          ) : (
            <div key={i} className="space-y-2">
              {item.parts.length === 0 && a.busy && <p className="animate-pulse-dot text-sm text-faint">Thinking…</p>}
              {item.parts.map((p, j) => (
                <PartView key={j} part={p} />
              ))}
            </div>
          ),
        )}
      </div>

      <form onSubmit={submit} className="border-t border-line p-3">
        <div className="flex gap-2">
          <textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) submit(e);
            }}
            rows={2}
            placeholder="Ask about a crane, an alarm or a procedure…"
            className="min-w-0 flex-1 resize-none rounded-lg border border-line bg-bg px-3 py-2 text-sm outline-none focus:border-crane"
          />
          <button
            type="submit"
            disabled={a.busy || !draft.trim()}
            className="self-end rounded-lg bg-crane px-3 py-2 text-sm font-medium text-bg disabled:opacity-50"
          >
            Send
          </button>
        </div>
        <p className="mt-2 text-[11px] text-faint">
          {a.mock ? "Mock mode (no API key): answers are assembled from tool results. " : ""}
          {a.budgetLeft !== null ? `${a.budgetLeft.toLocaleString()} tokens left today for your organisation.` : "AI can be wrong — check critical values on the crane."}
        </p>
      </form>
    </aside>
  );
}

function PartView({ part }: { part: Part }) {
  switch (part.type) {
    case "text":
      return <Markdown text={part.text} />;
    case "error":
      return <p className="rounded-md bg-crit/10 px-3 py-2 text-sm text-crit">{part.text}</p>;
    case "tool":
      return (
        <div className="flex items-center gap-2 text-xs text-muted" title={JSON.stringify(part.input)}>
          <span
            className={`size-1.5 rounded-full ${part.status === "running" ? "animate-pulse-dot bg-crane" : part.status === "ok" ? "bg-ok" : "bg-crit"}`}
          />
          <span className="font-medium text-ink">{TOOL_LABELS[part.name] ?? part.name}</span>
          {"crane_id" in part.input && <span className="font-mono">{String(part.input.crane_id)}</span>}
          {part.summary && <span className="truncate">· {part.summary}</span>}
        </div>
      );
    case "confirm":
      return <ConfirmAck part={part} />;
  }
}

/** Human in the loop: the model proposed a state change; only the user's click performs it. */
function ConfirmAck({ part }: { part: Extract<Part, { type: "confirm" }> }) {
  const { resolveConfirm } = useAssistant();
  const [pending, start] = useTransition();
  const alarmId = String(part.input.alarm_id ?? "");

  if (part.state !== "pending") {
    return (
      <p className={`text-sm ${part.state === "done" ? "text-ok" : "text-crit"}`}>
        {part.state === "done" ? `Alarm acknowledged${part.note ? ` by ${part.note}` : ""}.` : part.note}
      </p>
    );
  }
  return (
    <div className="rounded-lg border border-warn/40 bg-warn/10 p-3 text-sm">
      <p className="mb-2">
        Acknowledge alarm <span className="font-mono text-xs">{alarmId}</span>?
      </p>
      <div className="flex gap-2">
        <button
          type="button"
          disabled={pending}
          onClick={() =>
            start(async () => {
              const res = await acknowledgeAlarm(alarmId);
              resolveConfirm(part.id, res.ok ? "done" : "failed", res.ok ? (res.alarm.acknowledgedBy ?? undefined) : res.error);
            })
          }
          className="rounded-md bg-warn px-3 py-1 font-medium text-bg disabled:opacity-60"
        >
          {pending ? "Acknowledging…" : "Acknowledge"}
        </button>
        <button type="button" onClick={() => resolveConfirm(part.id, "failed", "Cancelled.")} className="rounded-md px-3 py-1 text-muted">
          Cancel
        </button>
      </div>
    </div>
  );
}

export function AssistantButton() {
  const { open, isOpen } = useAssistant();
  if (isOpen) return null;
  return (
    <button
      type="button"
      onClick={() => void open()}
      className="fixed right-5 bottom-5 z-20 flex items-center gap-2 rounded-full bg-crane px-4 py-2.5 text-sm font-medium text-bg shadow-lg transition hover:brightness-110"
    >
      <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
        <path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" strokeWidth="2" strokeLinejoin="round" />
      </svg>
      Ask AI
    </button>
  );
}
