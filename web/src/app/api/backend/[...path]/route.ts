import { cookies } from "next/headers";
import type { NextRequest } from "next/server";
import { API_URL } from "@/lib/api/server";
import { TOKEN_COOKIE } from "@/lib/api/session";

/**
 * Same-origin proxy for browser calls (assistant stream, snapshot, Reverb channel auth,
 * knowledge search). It only attaches the user's own token from the httpOnly cookie, so it
 * grants nothing the user couldn't do with the API directly — and the token never reaches
 * JavaScript. Responses are streamed through (SSE from the assistant).
 */
export const dynamic = "force-dynamic";
// Assistant turns with several tool calls can take a while (Vercel function limit).
export const maxDuration = 120;

const ALLOWED = /^(realtime\/snapshot|broadcasting\/auth|assistant\/conversations(\/[\w-]+(\/messages)?)?|knowledge\/search|reports(\/[\w-]+)?|alarms(\/[\w-]+\/ack)?)$/;

async function proxy(req: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const path = (await params).path.join("/");
  if (!ALLOWED.test(path)) return Response.json({ message: "Not found" }, { status: 404 });

  const bearer = (await cookies()).get(TOKEN_COOKIE)?.value;
  if (!bearer) return Response.json({ message: "Unauthenticated" }, { status: 401 });

  const upstream = await fetch(`${API_URL}/${path}${req.nextUrl.search}`, {
    method: req.method,
    headers: {
      Accept: req.headers.get("accept") ?? "application/json",
      "Content-Type": req.headers.get("content-type") ?? "application/json",
      Authorization: `Bearer ${bearer}`,
    },
    body: req.method === "GET" ? undefined : await req.text(),
    cache: "no-store",
    signal: req.signal,
  });

  const headers = new Headers();
  for (const h of ["content-type", "cache-control", "retry-after"]) {
    const v = upstream.headers.get(h);
    if (v) headers.set(h, v);
  }
  if (headers.get("content-type")?.includes("event-stream")) headers.set("X-Accel-Buffering", "no");
  return new Response(upstream.body, { status: upstream.status, headers });
}

export { proxy as GET, proxy as POST, proxy as DELETE };
