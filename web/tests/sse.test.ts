import { describe, expect, it } from "vitest";
import { createSseParser, type SseEvent } from "@/lib/assistant/sse";

describe("SSE parser", () => {
  it("handles events split across arbitrary chunks", () => {
    const events: SseEvent[] = [];
    const feed = createSseParser((e) => events.push(e));
    const stream = 'event: tool_call\ndata: {"name":"list_cranes"}\n\nevent: text\ndata: {"text":"Hi"}\n\n';
    for (const ch of stream) feed(ch);
    expect(events).toEqual([
      { event: "tool_call", data: '{"name":"list_cranes"}' },
      { event: "text", data: '{"text":"Hi"}' },
    ]);
  });

  it("joins multi-line data, ignores comments, accepts CRLF", () => {
    const events: SseEvent[] = [];
    const feed = createSseParser((e) => events.push(e));
    feed(": keep-alive\r\n\r\nevent: text\r\ndata: a\r\ndata: b\r\n\r\n");
    expect(events).toEqual([{ event: "text", data: "a\nb" }]);
  });

  it("buffers an incomplete trailing event", () => {
    const events: SseEvent[] = [];
    const feed = createSseParser((e) => events.push(e));
    feed("event: done\ndata: {}");
    expect(events).toHaveLength(0);
    feed("\n\n");
    expect(events).toHaveLength(1);
  });
});
