export const dynamic = "force-dynamic";

/** Liveness probe for Azure App Service / Container Apps. */
export function GET() {
  return Response.json({ status: "ok", ts: new Date().toISOString() });
}
