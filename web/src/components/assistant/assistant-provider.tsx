"use client";

import { createContext, use, useCallback, useRef, useState } from "react";
import { readSse } from "@/lib/assistant/sse";

export type Part =
  | { type: "text"; text: string }
  | { type: "tool"; id: string; name: string; input: Record<string, unknown>; status: "running" | "ok" | "error"; summary?: string }
  | { type: "confirm"; id: string; name: string; input: Record<string, unknown>; state: "pending" | "done" | "failed"; note?: string }
  | { type: "error"; text: string };

export type Item = { role: "user"; text: string } | { role: "assistant"; parts: Part[] };

export interface Focus {
  craneId?: string;
  alarmId?: string;
  title?: string;
}

interface AssistantState {
  isOpen: boolean;
  items: Item[];
  busy: boolean;
  focus: Focus | null;
  mock: boolean | null;
  budgetLeft: number | null;
  conversationId: string | null;
}

interface AssistantApi extends AssistantState {
  open: (focus?: Focus, prompt?: string) => Promise<void>;
  close: () => void;
  send: (text: string) => Promise<void>;
  newChat: () => void;
  load: (id: string) => Promise<void>;
  resolveConfirm: (id: string, state: "done" | "failed", note?: string) => void;
}

const Ctx = createContext<AssistantApi | null>(null);

export function useAssistant(): AssistantApi {
  const ctx = use(Ctx);
  if (!ctx) throw new Error("useAssistant must be used inside <AssistantProvider>");
  return ctx;
}

const json = (body: unknown) => ({
  method: "POST",
  headers: { "Content-Type": "application/json", Accept: "application/json" },
  body: JSON.stringify(body),
});

/**
 * Client state for the assistant panel. Conversations live on the API; this only renders
 * the streamed events (text, tool_call, tool_result, confirm, error, done).
 */
export function AssistantProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AssistantState>({
    isOpen: false,
    items: [],
    busy: false,
    focus: null,
    mock: null,
    budgetLeft: null,
    conversationId: null,
  });
  const conversation = useRef<string | null>(null);
  const focusRef = useRef<Focus | null>(null);
  const abort = useRef<AbortController | null>(null);

  const patchLast = (fn: (parts: Part[]) => Part[]) =>
    setState((s) => {
      const items = [...s.items];
      const last = items.at(-1);
      if (last?.role === "assistant") items[items.length - 1] = { role: "assistant", parts: fn(last.parts) };
      return { ...s, items };
    });

  const create = async () => {
    const focus = focusRef.current;
    const res = await fetch(
      "/api/backend/assistant/conversations",
      json({ crane_id: focus?.craneId ?? null, alarm_id: focus?.alarmId ?? null, title: focus?.title ?? null }),
    );
    if (!res.ok) throw new Error(res.status === 404 ? "That crane or alarm isn't available to you." : "Could not start a conversation.");
    const id = (await res.json()).conversation.id as string;
    conversation.current = id;
    setState((s) => ({ ...s, conversationId: id }));
    return id;
  };

  const send = useCallback(async (text: string) => {
    const message = text.trim();
    if (!message) return;
    setState((s) => ({ ...s, busy: true, items: [...s.items, { role: "user", text: message }, { role: "assistant", parts: [] }] }));
    abort.current = new AbortController();
    try {
      const id = conversation.current ?? (await create());
      const res = await fetch(`/api/backend/assistant/conversations/${id}/messages`, {
        ...json({ message }),
        headers: { "Content-Type": "application/json", Accept: "text/event-stream" },
        signal: abort.current.signal,
      });
      if (!res.ok || !res.body) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message ?? (res.status === 429 ? "Too many requests — try again in a minute." : `Request failed (${res.status})`));
      }
      await readSse(res.body, ({ event, data }) => {
        const d = JSON.parse(data);
        if (event === "text") patchLast((p) => [...p, { type: "text", text: d.text }]);
        else if (event === "tool_call") patchLast((p) => [...p, { type: "tool", id: d.id, name: d.name, input: d.input, status: "running" }]);
        else if (event === "tool_result")
          patchLast((p) => p.map((x) => (x.type === "tool" && x.id === d.id ? { ...x, status: d.ok ? "ok" : "error", summary: d.summary } : x)));
        else if (event === "confirm")
          patchLast((p) => [
            ...p.map((x) => (x.type === "tool" && x.id === d.id ? { ...x, status: "ok" as const, summary: "awaiting your confirmation" } : x)),
            { type: "confirm", id: d.id, name: d.name, input: d.input, state: "pending" },
          ]);
        else if (event === "error") patchLast((p) => [...p, { type: "error", text: d.message }]);
        else if (event === "done") setState((s) => ({ ...s, mock: d.mock, budgetLeft: d.budget_left }));
      });
    } catch (e) {
      if ((e as Error).name !== "AbortError") patchLast((p) => [...p, { type: "error", text: (e as Error).message }]);
    } finally {
      setState((s) => ({ ...s, busy: false }));
    }
  }, []);

  const open = useCallback(
    async (focus?: Focus, prompt?: string) => {
      if (focus) {
        // A new focus starts a new conversation so context doesn't bleed between alarms.
        abort.current?.abort();
        conversation.current = null;
        focusRef.current = focus;
        setState((s) => ({ ...s, isOpen: true, focus, items: [], conversationId: null }));
        if (prompt) await send(prompt);
      } else {
        setState((s) => ({ ...s, isOpen: true }));
      }
    },
    [send],
  );

  const newChat = useCallback(() => {
    abort.current?.abort();
    conversation.current = null;
    focusRef.current = null;
    setState((s) => ({ ...s, items: [], focus: null, conversationId: null }));
  }, []);

  const load = useCallback(async (id: string) => {
    const res = await fetch(`/api/backend/assistant/conversations/${id}`, { headers: { Accept: "application/json" } });
    if (!res.ok) return;
    const { conversation: c, messages } = await res.json();
    const items: Item[] = [];
    for (const m of messages as { role: string; content: { type: string; text?: string; id?: string; name?: string; input?: Record<string, unknown>; ok?: boolean }[] }[]) {
      if (m.role === "user") {
        const text = m.content.filter((b) => b.type === "text").map((b) => b.text).join("\n");
        if (text) items.push({ role: "user", text });
        // tool_result rows only update the status of the preceding tool chips
        for (const r of m.content.filter((b) => b.type === "tool_result")) {
          const last = items.at(-1);
          if (last?.role === "assistant")
            last.parts = last.parts.map((x) => (x.type === "tool" && x.id === r.id ? { ...x, status: r.ok ? "ok" : "error" } : x));
        }
      } else {
        const parts: Part[] = m.content.map((b) =>
          b.type === "tool_call"
            ? { type: "tool", id: b.id!, name: b.name!, input: b.input ?? {}, status: "running" }
            : { type: "text", text: b.text ?? "" },
        );
        const last = items.at(-1);
        if (last?.role === "assistant") last.parts.push(...parts);
        else items.push({ role: "assistant", parts });
      }
    }
    conversation.current = c.id;
    setState((s) => ({ ...s, items, conversationId: c.id, focus: c.crane_id || c.alarm_id ? { craneId: c.crane_id, alarmId: c.alarm_id, title: c.title } : null }));
  }, []);

  const resolveConfirm = useCallback(
    (id: string, result: "done" | "failed", note?: string) =>
      setState((s) => ({
        ...s,
        items: s.items.map((it) =>
          it.role === "assistant" ? { ...it, parts: it.parts.map((p) => (p.type === "confirm" && p.id === id ? { ...p, state: result, note } : p)) } : it,
        ),
      })),
    [],
  );

  const api: AssistantApi = {
    ...state,
    open,
    close: () => setState((s) => ({ ...s, isOpen: false })),
    send: (t) => send(t),
    newChat,
    load,
    resolveConfirm,
  };
  return <Ctx value={api}>{children}</Ctx>;
}
