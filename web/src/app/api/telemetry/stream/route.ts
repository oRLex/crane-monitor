import type { NextRequest } from "next/server";
import { can } from "@/lib/auth/rbac";
import { getPrincipal } from "@/lib/auth/session";
import { visibleCranes } from "@/lib/access";
import { getHub } from "@/lib/telemetry";
import type { RealtimeEvent } from "@/lib/domain";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";
// Vercel cuts serverless functions at this limit (seconds); EventSource reconnects automatically.
export const maxDuration = 300;

/**
 * Server-Sent Events endpoint: the built-in fallback transport.
 * Mirrors the SignalR hub contract (snapshot / telemetry / alarm events), so the
 * dashboard can switch transports via NEXT_PUBLIC_REALTIME without code changes.
 */
export async function GET(req: NextRequest) {
  const principal = await getPrincipal();
  if (!principal) return new Response("Unauthorized", { status: 401 });
  if (!can(principal, "telemetry:read")) return new Response("Forbidden", { status: 403 });

  const allowed = new Set((await visibleCranes(principal)).map((c) => c.id));
  const requested = req.nextUrl.searchParams.getAll("crane");
  const craneIds = requested.length ? requested.filter((id) => allowed.has(id)) : [...allowed];

  const hub = await getHub();
  const encoder = new TextEncoder();
  let cleanup = () => {};

  const stream = new ReadableStream<Uint8Array>({
    start(controller) {
      const send = (event: RealtimeEvent) => {
        try {
          controller.enqueue(encoder.encode(`event: ${event.type}\ndata: ${JSON.stringify(event)}\n\n`));
        } catch {
          cleanup();
        }
      };
      const unsubscribe = hub.subscribe(craneIds, send);
      // Comment frames keep proxies (Azure Front Door / App Gateway) from idling the connection out.
      const heartbeat = setInterval(() => {
        try {
          controller.enqueue(encoder.encode(`: ping\n\n`));
        } catch {
          cleanup();
        }
      }, 15_000);
      cleanup = () => {
        clearInterval(heartbeat);
        unsubscribe();
        cleanup = () => {};
      };
      req.signal.addEventListener("abort", () => {
        cleanup();
        try {
          controller.close();
        } catch {}
      });
    },
    cancel() {
      cleanup();
    },
  });

  return new Response(stream, {
    headers: {
      "Content-Type": "text/event-stream; charset=utf-8",
      "Cache-Control": "no-cache, no-transform",
      Connection: "keep-alive",
      "X-Accel-Buffering": "no",
    },
  });
}
