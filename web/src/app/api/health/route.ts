export const dynamic = "force-dynamic";

/** Liveness probe (Vercel / container healthcheck). */
export function GET() {
  return Response.json({ status: "ok", ts: new Date().toISOString() });
}
